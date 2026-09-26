<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Contracts;

/**
 * The candidates of a booking at one start, which a hold locks before it
 * claims (SlotClaims::scope()).
 */
final class ClaimScope
{
    /**
     * @param list<int> $staffIds
     * @param list<int> $resourceIds
     * @param int $from UTC seconds, the earliest a booking takes anyone, buffer included.
     * @param int $to UTC seconds, the latest.
     */
    public function __construct(
        public readonly array $staffIds,
        public readonly array $resourceIds,
        public readonly int $from,
        public readonly int $to,
    ) {
    }
}
