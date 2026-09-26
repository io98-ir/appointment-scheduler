<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain\Availability;

use Vaqtyar\Shared\Domain\IntervalSet;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * One resource of a group, on one day: its working and blocked time in UTC
 * seconds and what is booked on it.
 */
final class ResourceCandidate
{
    /**
     * @param int $capacity sessions it hosts at the same time, whatever was
     *     booked; the customers of one group session count once.
     * @param list<Occupancy> $occupancies
     */
    public function __construct(
        public readonly int $resourceId,
        public readonly int $capacity,
        public readonly IntervalSet $working,
        public readonly IntervalSet $blocked,
        public readonly array $occupancies,
    ) {
        if ($capacity < 1) {
            throw new InvalidValue('invalid_capacity', 'A capacity is at least one.');
        }
    }
}
