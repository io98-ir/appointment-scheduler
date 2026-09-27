<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;

/**
 * What a hold keeps for its booking (HoldRepository::details()); times
 * are UTC seconds.
 */
final class HeldBooking
{
    /**
     * @param list<int> $extraIds an id once per unit.
     */
    public function __construct(
        public readonly int $locationId,
        public readonly int $variantId,
        public readonly int $staffId,
        public readonly int $start,
        public readonly int $end,
        public readonly int $partySize,
        public readonly array $extraIds,
        public readonly PriceQuote $quote,
    ) {
    }
}
