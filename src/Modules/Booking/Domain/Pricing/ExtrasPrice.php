<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

/**
 * Step 4 (booking-engine §5): a line per chosen extra, its unit price times
 * its units.
 */
final class ExtrasPrice implements PriceRule
{
    public function apply(PriceContext $context, PriceQuote $quote): PriceQuote
    {
        foreach ($context->extras as $extra) {
            $quote = $quote->with(new PriceLine(
                PriceLine::EXTRA,
                $extra->unitPrice->multiply($extra->qty),
                $extra->extraId,
                $extra->qty
            ));
        }

        return $quote;
    }
}
