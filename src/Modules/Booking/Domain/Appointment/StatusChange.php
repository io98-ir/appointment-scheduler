<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Appointment;

/**
 * One transition, as appointment_history records it.
 */
final class StatusChange
{
    /**
     * @param ?AppointmentStatus $from null when the appointment was created.
     */
    public function __construct(
        public readonly string $action,
        public readonly ?AppointmentStatus $from,
        public readonly AppointmentStatus $to,
    ) {
    }
}
