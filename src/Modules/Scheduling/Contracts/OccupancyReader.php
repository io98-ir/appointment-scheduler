<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Contracts;

/**
 * Where availability reads what is booked: the occupancies of appointments
 * and unexpired holds (data-model §2). Booking owns them and implements
 * this, so Scheduling does not depend on Booking (architecture §3).
 */
interface OccupancyReader
{
    /**
     * One query for every staff member and resource of a request.
     *
     * @param list<int> $staffIds
     * @param list<int> $resourceIds
     * @param int $from UTC seconds.
     * @param int $to UTC seconds.
     * @return list<BusySpan> Those that overlap [from, to), holds expired by now left out.
     */
    public function overlapping(array $staffIds, array $resourceIds, int $from, int $to): array;
}
