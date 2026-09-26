<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain\Availability;

/**
 * What one booking at a start takes (AvailabilityCalculator::pick()).
 */
final class Pick
{
    /**
     * @param int $start UTC seconds.
     * @param list<int> $resourceIds one unit per needed quantity, in group order.
     */
    public function __construct(
        public readonly int $start,
        public readonly SlotStaff $staff,
        public readonly array $resourceIds,
    ) {
    }
}
