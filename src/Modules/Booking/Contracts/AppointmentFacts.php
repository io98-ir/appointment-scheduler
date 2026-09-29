<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Contracts;

/**
 * What a message about an appointment says: the appointment, the customer
 * and the names of what was booked. Read once, at the moment of sending, so
 * a message never states an old time.
 */
final class AppointmentFacts
{
    /**
     * @param string $status an AppointmentStatus value, e.g. "confirmed".
     * @param int $start UTC seconds.
     * @param int $end UTC seconds.
     * @param string $timezone the location's, in which start and end are shown.
     * @param ?string $customerPhone E.164; null when the customer is deleted.
     * @param ?string $customerEmail null when missing or the customer is deleted.
     * @param int $total in IRR.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $status,
        public readonly int $start,
        public readonly int $end,
        public readonly string $timezone,
        public readonly int $partySize,
        public readonly int $total,
        public readonly string $customerName,
        public readonly ?string $customerPhone,
        public readonly ?string $customerEmail,
        public readonly string $serviceName,
        public readonly string $locationName,
        public readonly string $staffName,
        public readonly ?string $staffEmail,
    ) {
    }
}
