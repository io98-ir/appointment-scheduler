<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain\Availability;

use Vaqtyar\Shared\Domain\IntervalSet;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * A staff member who serves the variant, on one day: their own duration
 * (Service::terms()), their working and blocked time in UTC seconds
 * (DayPlan through LocalDay, within the location's hours), and what is
 * booked with them.
 */
final class StaffCandidate
{
    /**
     * @param list<Occupancy> $occupancies
     * @param int $priority lower comes first.
     */
    public function __construct(
        public readonly int $staffId,
        public readonly int $durationMin,
        public readonly IntervalSet $working,
        public readonly IntervalSet $blocked,
        public readonly array $occupancies,
        public readonly int $priority = 0,
    ) {
        if ($durationMin < 1) {
            throw new InvalidValue('invalid_minutes', 'A duration is at least one minute.');
        }
    }
}
