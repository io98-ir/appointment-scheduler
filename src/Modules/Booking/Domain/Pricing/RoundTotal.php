<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Rounding;

/**
 * Step 7 (booking-engine §5): the total to a multiple of $step rials, e.g.
 * 10,000 for whole thousand tomans, with the difference as its own line.
 */
final class RoundTotal implements PriceRule
{
    public function __construct(private readonly int $step, private readonly Rounding $rounding)
    {
        if ($step < 1) {
            throw new InvalidValue('invalid_rounding_step', 'A rounding step is at least one rial.');
        }
    }

    public function apply(PriceContext $context, PriceQuote $quote): PriceQuote
    {
        $total = $quote->total()->amount;
        $down = \intdiv($total, $this->step) * $this->step;
        $remainder = $total - $down;
        $rounded = match ($this->rounding) {
            Rounding::Down => $down,
            Rounding::Up => 0 === $remainder ? $down : $down + $this->step,
            Rounding::HalfUp => $remainder * 2 >= $this->step ? $down + $this->step : $down,
        };

        return $rounded === $total
            ? $quote
            : $quote->with(new PriceLine(PriceLine::ROUNDING, Money::ofRial($rounded - $total)));
    }
}
