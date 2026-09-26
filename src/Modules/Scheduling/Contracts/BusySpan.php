<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Contracts;

/**
 * One occupancy of a staff member or a resource (OccupancyReader): UTC
 * seconds [start, end), buffers included.
 */
final class BusySpan
{
    /**
     * @param bool $onResource whether $ownerId is a resource, else a staff member.
     * @param int $seats the party size booked.
     * @param ?int $variantId what was booked.
     * @param ?int $staffId who serves it, which tells one group session from another.
     */
    public function __construct(
        public readonly bool $onResource,
        public readonly int $ownerId,
        public readonly int $start,
        public readonly int $end,
        public readonly int $seats,
        public readonly ?int $variantId,
        public readonly ?int $staffId,
    ) {
    }
}
