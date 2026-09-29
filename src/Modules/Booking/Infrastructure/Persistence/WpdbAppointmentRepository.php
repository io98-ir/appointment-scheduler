<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\Actor;
use Vaqtyar\Modules\Booking\Application\AppointmentRepository;
use Vaqtyar\Modules\Booking\Application\StoredAppointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\PaymentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\StatusChange;
use Vaqtyar\Modules\Booking\Domain\Appointment\TrackingCode;
use Vaqtyar\Modules\Booking\Domain\LockKey;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceLine;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Modules\Scheduling\Contracts\Claim;
use Vaqtyar\Shared\Domain\Ulid;

/**
 * AppointmentRepository on appointments, appointment_extras and
 * appointment_history (data-model §2). The price is the hold's quote:
 * its total, its lines as JSON, and one extras row per extra line with
 * the unit price it was sold at. Its occupancies have owner_type
 * "appointment" and no expiry; cancelling deletes them.
 */
final class WpdbAppointmentRepository implements AppointmentRepository
{
    private const UTC_FORMAT = 'Y-m-d H:i:s';

    private const OWNER_TYPE = 'appointment';

    public function __construct(private readonly Db $db)
    {
    }

    public function add(Appointment $appointment, StatusChange $change, string $source, ?int $userId, int $now): int
    {
        $at = \gmdate(self::UTC_FORMAT, $now);
        $quote = $appointment->quote->toArray();
        $id = $this->db->insert(Tables::name('appointments'), [
            'uuid' => $appointment->uuid->toString(),
            'code' => $appointment->code->value,
            'customer_id' => $appointment->customerId,
            'location_id' => $appointment->locationId,
            'service_id' => $appointment->serviceId,
            'variant_id' => $appointment->variantId,
            'staff_id' => $appointment->staffId,
            'status' => $appointment->status()->value,
            'payment_status' => $appointment->paymentStatus()->value,
            'source' => $source,
            'start_at' => \gmdate(self::UTC_FORMAT, $appointment->start),
            'end_at' => \gmdate(self::UTC_FORMAT, $appointment->end),
            'local_date' => $appointment->localDate,
            'timezone' => $appointment->timezone,
            'party_size' => $appointment->partySize,
            'price_total' => $quote['total']['amount'],
            'price_lines' => (string) \json_encode($quote['lines'], \JSON_THROW_ON_ERROR),
            'customer_note' => $appointment->customerNote,
            'internal_note' => '',
            'created_by' => $userId,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        foreach ($appointment->quote->lines as $line) {
            if (PriceLine::EXTRA !== $line->code || null === $line->ref) {
                continue;
            }
            $this->db->insert(Tables::name('appointment_extras'), [
                'appointment_id' => $id,
                'extra_id' => $line->ref,
                'qty' => $line->qty,
                // The unit price: an extra line is always unit price × qty (ExtrasPrice).
                'price' => \intdiv($line->amount->amount, \max(1, $line->qty)),
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
        $this->history($id, $change, null === $userId ? 'system' : Actor::USER, $userId, null, [], $at);

        return $id;
    }

    /**
     * @param array<string, string> $answers
     */
    public function saveAnswers(int $appointmentId, array $answers, int $now): void
    {
        $at = \gmdate(self::UTC_FORMAT, $now);
        foreach ($answers as $fieldKey => $value) {
            $this->db->insert(Tables::name('appointment_answers'), [
                'appointment_id' => $appointmentId,
                'field_key' => $fieldKey,
                'value' => $value,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    public function find(int $id, bool $forUpdate = false): ?StoredAppointment
    {
        $sql = 'SELECT uuid, code, customer_id, location_id, service_id, variant_id, staff_id, status, payment_status,
            start_at, end_at, local_date, timezone, party_size, price_lines, customer_note, cancelled_at, cancel_reason
            FROM %i WHERE id = %d';
        $rows = $this->db->getResults(
            $forUpdate ? $sql . ' FOR UPDATE' : $sql,
            Tables::name('appointments'),
            $id
        );
        if ([] === $rows) {
            return null;
        }
        $row = new Row($rows[0]);
        $lines = \json_decode($row->string('price_lines'), true);
        $cancelledAt = $row->stringOrNull('cancelled_at');
        $appointment = Appointment::restore(
            Ulid::fromString($row->string('uuid')),
            TrackingCode::fromString($row->string('code')),
            $row->int('customer_id'),
            $row->int('location_id'),
            $row->int('service_id'),
            $row->int('variant_id'),
            $row->int('staff_id'),
            self::timestamp($row->string('start_at')),
            self::timestamp($row->string('end_at')),
            $row->string('local_date'),
            $row->string('timezone'),
            $row->int('party_size'),
            PriceQuote::fromArray(['lines' => \is_array($lines) ? $lines : []]),
            $row->string('customer_note'),
            PaymentStatus::from($row->string('payment_status')),
            AppointmentStatus::from($row->string('status')),
            null === $cancelledAt ? null : self::timestamp($cancelledAt),
            $row->stringOrNull('cancel_reason')
        );

        $keys = [];
        $from = \PHP_INT_MAX;
        $to = 0;
        $occupancies = $this->db->getResults(
            'SELECT lock_key, start_at, end_at FROM %i WHERE owner_type = %s AND owner_id = %d',
            Tables::name('occupancies'),
            self::OWNER_TYPE,
            $id
        );
        foreach ($occupancies as $values) {
            $occupancy = new Row($values);
            $keys[] = $occupancy->string('lock_key');
            $from = \min($from, self::timestamp($occupancy->string('start_at')));
            $to = \max($to, self::timestamp($occupancy->string('end_at')));
        }
        \sort($keys, \SORT_STRING);
        $extras = [];
        $extraRows = $this->db->getResults(
            'SELECT extra_id, qty FROM %i WHERE appointment_id = %d ORDER BY id',
            Tables::name('appointment_extras'),
            $id
        );
        foreach ($extraRows as $values) {
            $extra = new Row($values);
            $extras = [...$extras, ...\array_fill(0, $extra->int('qty'), $extra->int('extra_id'))];
        }
        $rescheduled = (int) $this->db->getVar(
            'SELECT COUNT(*) FROM %i WHERE appointment_id = %d AND action = %s',
            Tables::name('appointment_history'),
            $id,
            'reschedule'
        );

        return new StoredAppointment($id, $appointment, $keys, [] === $keys ? 0 : $from, $to, $extras, $rescheduled);
    }

    /**
     * @param array<string, array{int|string|null, int|string|null}> $changes
     */
    public function update(
        int $id,
        Appointment $appointment,
        StatusChange $change,
        Actor $actor,
        ?string $reason,
        array $changes,
        int $now,
    ): void {
        $at = \gmdate(self::UTC_FORMAT, $now);
        $cancelledAt = $appointment->cancelledAt();
        $this->db->update(
            Tables::name('appointments'),
            [
                'status' => $appointment->status()->value,
                'payment_status' => $appointment->paymentStatus()->value,
                'staff_id' => $appointment->staffId,
                'start_at' => \gmdate(self::UTC_FORMAT, $appointment->start),
                'end_at' => \gmdate(self::UTC_FORMAT, $appointment->end),
                'local_date' => $appointment->localDate,
                'cancelled_at' => null === $cancelledAt ? null : \gmdate(self::UTC_FORMAT, $cancelledAt),
                'cancel_reason' => $appointment->cancelReason(),
                'updated_at' => $at,
            ],
            ['id' => $id]
        );
        $this->db->execute('UPDATE %i SET version = version + 1 WHERE id = %d', Tables::name('appointments'), $id);
        $this->history($id, $change, $actor->type, 0 === $actor->id ? null : $actor->id, $reason, $changes, $at);
    }

    /**
     * @return list<int>
     */
    public function pendingPaymentBefore(int $cutoff, int $limit): array
    {
        $ids = [];
        foreach (
            $this->db->getResults(
                'SELECT id FROM %i WHERE status = %s AND created_at < %s ORDER BY id ASC LIMIT %d',
                Tables::name('appointments'),
                AppointmentStatus::PendingPayment->value,
                \gmdate(self::UTC_FORMAT, $cutoff),
                $limit
            ) as $row
        ) {
            $ids[] = (new Row($row))->int('id');
        }

        return $ids;
    }

    public function release(int $id): void
    {
        $this->db->execute(
            'DELETE FROM %i WHERE owner_type = %s AND owner_id = %d',
            Tables::name('occupancies'),
            self::OWNER_TYPE,
            $id
        );
    }

    public function occupy(int $id, Claim $claim, int $variantId, int $partySize): void
    {
        foreach (LockKey::sorted([$claim->staffId], $claim->resourceIds) as $key) {
            $this->db->insert(Tables::name('occupancies'), [
                'owner_type' => self::OWNER_TYPE,
                'owner_id' => $id,
                'lock_key' => $key,
                'variant_id' => $variantId,
                'staff_id' => $claim->staffId,
                'start_at' => \gmdate(self::UTC_FORMAT, $claim->from),
                'end_at' => \gmdate(self::UTC_FORMAT, $claim->to),
                'seats' => $partySize,
                'expires_at' => null,
            ]);
        }
    }

    public function saveNote(int $id, Appointment $appointment, string $note, Actor $actor, int $now): void
    {
        $at = \gmdate(self::UTC_FORMAT, $now);
        $this->db->update(
            Tables::name('appointments'),
            ['internal_note' => $note, 'updated_at' => $at],
            ['id' => $id]
        );
        $this->db->execute('UPDATE %i SET version = version + 1 WHERE id = %d', Tables::name('appointments'), $id);
        $status = $appointment->status();
        $this->history($id, new StatusChange('note', $status, $status), $actor->type, $actor->id, null, [], $at);
    }

    /**
     * @param array<string, array{int|string|null, int|string|null}> $changes
     */
    private function history(
        int $id,
        StatusChange $change,
        string $actorType,
        ?int $actorId,
        ?string $reason,
        array $changes,
        string $at,
    ): void {
        $this->db->insert(Tables::name('appointment_history'), [
            'appointment_id' => $id,
            'action' => $change->action,
            'from_status' => $change->from?->value,
            'to_status' => $change->to->value,
            'changes' => [] === $changes ? null : (string) \json_encode($changes, \JSON_THROW_ON_ERROR),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'reason' => $reason,
            'created_at' => $at,
        ]);
    }

    private static function timestamp(string $utc): int
    {
        $time = \DateTimeImmutable::createFromFormat('!' . self::UTC_FORMAT, $utc, new \DateTimeZone('UTC'));
        if (false === $time) {
            throw new \UnexpectedValueException('A DATETIME column is not a date.');
        }

        return $time->getTimestamp();
    }
}
