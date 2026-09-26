<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

use Vaqtyar\Shared\Domain\Rounding;

/**
 * Step 6 (booking-engine §5): the coupon, if any, takes its discount off
 * the total so far, down to zero at most. An unusable coupon is refused
 * rather than ignored, so the customer knows why.
 */
final class CouponDiscount implements PriceRule
{
    public function __construct(private readonly Rounding $rounding)
    {
    }

    public function apply(PriceContext $context, PriceQuote $quote): PriceQuote
    {
        $coupon = $context->coupon;
        if (null === $coupon) {
            return $quote;
        }
        $coupon->assertUsable($context->serviceId, $context->now);
        $discount = $coupon->discountOn($quote->total(), $this->rounding);

        return $discount->isZero()
            ? $quote
            : $quote->with(new PriceLine(PriceLine::COUPON, $discount->multiply(-1), $coupon->id));
    }
}
