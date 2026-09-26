<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Contracts;

/**
 * What a booking at one start takes (SlotClaims::claim()), in UTC seconds.
 */
final class Claim
{
    /**
     * @param int $end the staff member's duration plus extras, without the buffer.
     * @param int $from $start minus the buffer before: the start of every occupancy.
     * @param int $to $end plus the buffer after: the end of every occupancy.
     * @param list<int> $resourceIds
     */
    public function __construct(
        public readonly int $staffId,
        public readonly int $start,
        public readonly int $end,
        public readonly int $from,
        public readonly int $to,
        public readonly array $resourceIds,
    ) {
    }
}
