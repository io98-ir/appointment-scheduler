<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use DateTimeImmutable;
use DateTimeZone;
use Vaqtyar\Modules\Booking\Application\AppointmentDetail;
use Vaqtyar\Modules\Booking\Application\AppointmentRow;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Shared\Domain\Money;

/**
 * An appointment as the REST routes return it (docs/api.md): times with the
 * location's offset, money as {amount, currency}.
 */
final class AppointmentJson
{
    /**
     * @return array<string, mixed>
     */
    public static function of(int $id, Appointment $appointment): array
    {
        $time = self::clock($appointment->timezone);

        return [
            'id' => $id,
            'uuid' => $appointment->uuid->toString(),
            'code' => $appointment->code->value,
            'status' => $appointment->status()->value,
            'payment_status' => $appointment->paymentStatus->value,
            'staff_id' => $appointment->staffId,
            'start' => $time($appointment->start),
            'end' => $time($appointment->end),
            'price' => $appointment->quote->toArray(),
        ];
    }

    /**
     * An item of the admin list and the calendar.
     *
     * @return array<string, mixed>
     */
    public static function row(AppointmentRow $row): array
    {
        $time = self::clock($row->timezone);
        $customer = $row->customer;

        return [
            'id' => $row->id,
            'code' => $row->code,
            'status' => $row->status->value,
            'payment_status' => $row->paymentStatus->value,
            'customer_id' => $row->customerId,
            'customer' => null === $customer ? null : [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'deleted' => $customer->deleted,
            ],
            'location_id' => $row->locationId,
            'service_id' => $row->serviceId,
            'variant_id' => $row->variantId,
            'staff_id' => $row->staffId,
            'start' => $time($row->start),
            'end' => $time($row->end),
            'party_size' => $row->partySize,
            'total' => $row->total->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(AppointmentDetail $detail): array
    {
        $time = self::clock($detail->row->timezone);

        return self::row($detail->row) + [
            'uuid' => $detail->uuid,
            'source' => $detail->source,
            'price' => $detail->quote->toArray(),
            'customer_note' => $detail->customerNote,
            'internal_note' => $detail->internalNote,
            'extras' => \array_map(static fn (array $extra): array => [
                'extra_id' => $extra['extra_id'],
                'qty' => $extra['qty'],
                'unit_price' => Money::ofRial($extra['unit_price'])->toArray(),
            ], $detail->extras),
            // An object even when empty, as the field keys are its keys.
            'answers' => (object) $detail->answers,
            'history' => \array_map(static fn (array $change): array => [
                'action' => $change['action'],
                'from' => $change['from']?->value,
                'to' => $change['to']?->value,
                'changes' => $change['changes'],
                'actor_type' => $change['actor_type'],
                'actor_id' => $change['actor_id'],
                'reason' => $change['reason'],
                'at' => $time($change['at']),
            ], $detail->history),
            'created_by' => $detail->createdBy,
            'created_at' => $time($detail->createdAt),
            'cancelled_at' => null === $detail->cancelledAt ? null : $time($detail->cancelledAt),
            'cancel_reason' => $detail->cancelReason,
        ];
    }

    /**
     * @return \Closure(int): string UTC seconds as ISO 8601 in the zone.
     */
    private static function clock(string $timezone): \Closure
    {
        $zone = new DateTimeZone($timezone);

        return static fn (int $timestamp): string => (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone($zone)
            ->format(\DATE_ATOM);
    }
}
