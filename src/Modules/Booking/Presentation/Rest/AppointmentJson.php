<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use DateTimeImmutable;
use DateTimeZone;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;

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
        $zone = new DateTimeZone($appointment->timezone);
        $time = static fn (int $timestamp): string => (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone($zone)
            ->format(\DATE_ATOM);

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
}
