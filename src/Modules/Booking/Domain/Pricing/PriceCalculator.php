<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Rounding;

/**
 * The price of a booking (booking-engine §5): its rules in order, each
 * adding lines to the quote. Money is integer rials throughout, and every
 * percentage names its rounding.
 */
final class PriceCalculator
{
    /**
     * @param list<PriceRule> $rules
     */
    public function __construct(private readonly array $rules)
    {
    }

    /**
     * The steps of booking-engine §5 in their order: base (variant or staff
     * price), time rules, extras, party size, coupon, rounding.
     *
     * @param list<TimeRule> $timeRules highest priority first.
     * @param int $roundingStep rials; 1 leaves the total as it is.
     */
    public static function standard(array $timeRules, int $roundingStep, Rounding $rounding): self
    {
        return new self([
            new BasePrice(),
            new TimePricing($timeRules, Rounding::HalfUp),
            new ExtrasPrice(),
            new PartySize(),
            // The customer's discount is never rounded up in their disfavour.
            new CouponDiscount(Rounding::Up),
            new RoundTotal($roundingStep, $rounding),
        ]);
    }

    public function quote(PriceContext $context): PriceQuote
    {
        $quote = PriceQuote::empty();
        foreach ($this->rules as $rule) {
            $quote = $rule->apply($context, $quote);
        }
        if ($quote->total()->isNegative()) {
            throw new InvalidValue('negative_price', 'The price came out below zero.');
        }

        return $quote;
    }
}
