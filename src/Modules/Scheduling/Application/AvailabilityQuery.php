<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Application;

/**
 * What a customer asks availability for (booking-engine §2).
 */
final class AvailabilityQuery
{
    /**
     * @param ?int $staffId a chosen staff member; null lets the business choose.
     * @param list<int> $extraIds the chosen extras, an id once per unit.
     */
    public function __construct(
        public readonly int $variantId,
        public readonly int $locationId,
        public readonly ?int $staffId = null,
        public readonly array $extraIds = [],
        public readonly int $partySize = 1,
    ) {
    }
}
