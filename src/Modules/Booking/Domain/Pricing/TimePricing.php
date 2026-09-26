<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

use Vaqtyar\Shared\Domain\Rounding;

/**
 * Step 3 (booking-engine §5): the first time rule, in priority order, that
 * matches the start changes the base price by its percentage. Only one
 * applies, so overlapping rules never stack by accident.
 */
final class TimePricing implements PriceRule
{
    /**
     * @param list<TimeRule> $rules highest priority first.
     */
    public function __construct(private readonly array $rules, private readonly Rounding $rounding)
    {
    }

    public function apply(PriceContext $context, PriceQuote $quote): PriceQuote
    {
        foreach ($this->rules as $rule) {
            if (!$rule->matches($context)) {
                continue;
            }
            $change = $context->basePrice->percent(\abs($rule->percent), $this->rounding);
            if ($rule->percent < 0) {
                $change = $change->multiply(-1);
            }

            return $change->isZero()
                ? $quote
                : $quote->with(new PriceLine(PriceLine::TIME_RULE, $change, $rule->id));
        }

        return $quote;
    }
}
