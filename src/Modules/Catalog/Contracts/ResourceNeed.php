<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Contracts;

/**
 * A variant needs this many resources of a group; any active member of the
 * group can serve (booking-engine §1).
 */
final class ResourceNeed
{
    /**
     * @param list<ResourceUnit> $units the group's active resources; fewer than
     *     $quantity means the variant cannot be booked.
     */
    public function __construct(
        public readonly string $groupKey,
        public readonly int $quantity,
        public readonly array $units,
    ) {
    }
}
