<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

/**
 * Steps 1 and 2 (booking-engine §5): the variant's price, or the staff
 * member's own price for it, which the context already carries.
 */
final class BasePrice implements PriceRule
{
    public function apply(PriceContext $context, PriceQuote $quote): PriceQuote
    {
        return $quote->with(new PriceLine(PriceLine::BASE, $context->basePrice));
    }
}
