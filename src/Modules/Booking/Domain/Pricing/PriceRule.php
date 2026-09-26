<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

/**
 * One step of PriceCalculator (booking-engine §5). Add-ons may add their own.
 */
interface PriceRule
{
    /**
     * @return PriceQuote $quote with this step's lines added, if any.
     */
    public function apply(PriceContext $context, PriceQuote $quote): PriceQuote;
}
