<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Contracts;

/**
 * One resource of a group.
 */
final class ResourceUnit
{
    /**
     * @param ?int $locationId null for a resource every location shares.
     * @param int $capacity bookings it holds at the same time.
     */
    public function __construct(
        public readonly int $resourceId,
        public readonly ?int $locationId,
        public readonly int $capacity,
    ) {
    }
}
