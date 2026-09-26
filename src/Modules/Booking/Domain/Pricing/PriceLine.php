<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

use Vaqtyar\Shared\Domain\Money;

/**
 * One line of a price breakdown, shown to the customer and the staff
 * (booking-engine §5). A discount is negative.
 */
final class PriceLine
{
    public const BASE = 'base';
    public const TIME_RULE = 'time_rule';
    public const EXTRA = 'extra';
    public const PARTY = 'party';
    public const COUPON = 'coupon';
    public const ROUNDING = 'rounding';

    /**
     * @param string $code one of the constants: what the line is for.
     * @param ?int $ref the extra, price rule or coupon it comes from.
     * @param int $qty units, for an extra; people, for the party line.
     */
    public function __construct(
        public readonly string $code,
        public readonly Money $amount,
        public readonly ?int $ref = null,
        public readonly int $qty = 1,
    ) {
    }

    /**
     * @return array{code: string, amount: array{amount: int, currency: string}, ref: ?int, qty: int}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'amount' => $this->amount->toArray(), 'ref' => $this->ref, 'qty' => $this->qty];
    }
}
