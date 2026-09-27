<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;

/**
 * An appointment as read back (AppointmentRepository::find()), with what
 * changing it needs; times are UTC seconds.
 */
final class StoredAppointment
{
    /**
     * @param list<string> $lockKeys of its occupancies; empty once cancelled.
     * @param int $from the earliest start of its occupancies.
     * @param int $to the latest end.
     * @param list<int> $extraIds an id once per unit.
     * @param int $rescheduled how many times it was moved.
     */
    public function __construct(
        public readonly int $id,
        public readonly Appointment $appointment,
        public readonly array $lockKeys,
        public readonly int $from,
        public readonly int $to,
        public readonly array $extraIds,
        public readonly int $rescheduled,
    ) {
    }
}
