<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\AppointmentRepository;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\StatusChange;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceLine;

/**
 * AppointmentRepository on appointments, appointment_extras and
 * appointment_history (data-model §2). The price is the hold's quote:
 * its total, its lines as JSON, and one extras row per extra line with
 * the unit price it was sold at.
 */
final class WpdbAppointmentRepository implements AppointmentRepository
{
    private const UTC_FORMAT = 'Y-m-d H:i:s';

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
            'payment_status' => $appointment->paymentStatus->value,
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
        $this->db->insert(Tables::name('appointment_history'), [
            'appointment_id' => $id,
            'action' => $change->action,
            'from_status' => $change->from?->value,
            'to_status' => $change->to->value,
            'actor_type' => null === $userId ? 'system' : 'user',
            'actor_id' => $userId,
            'created_at' => $at,
        ]);

        return $id;
    }
}
