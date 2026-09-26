<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

/**
 * Step 5 (booking-engine §5): every person pays the same, extras included,
 * so the other people of the party add the total so far once each.
 */
final class PartySize implements PriceRule
{
    public function apply(PriceContext $context, PriceQuote $quote): PriceQuote
    {
        if (1 === $context->partySize) {
            return $quote;
        }
        $others = $context->partySize - 1;

        return $quote->with(new PriceLine(PriceLine::PARTY, $quote->total()->multiply($others), null, $others));
    }
}
