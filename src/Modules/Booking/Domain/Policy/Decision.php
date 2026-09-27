<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Policy;

use Vaqtyar\Shared\Domain\Money;

/**
 * What a policy says about a change (booking-engine §6), as the UI shows
 * it: whether it is allowed, why not, and what would be refunded.
 */
final class Decision
{
    /**
     * @param ?string $reasonCode why not, e.g. policy.cancel_window_passed; null when allowed.
     * @param int $refundPercent of what was paid, 0 to 100.
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reasonCode,
        public readonly int $refundPercent,
        public readonly Money $refund,
    ) {
    }

    public static function allow(int $refundPercent = 0, ?Money $refund = null): self
    {
        return new self(true, null, $refundPercent, $refund ?? Money::zero());
    }

    public static function deny(string $reasonCode): self
    {
        return new self(false, $reasonCode, 0, Money::zero());
    }

    /**
     * @return array{
     *     allowed: bool,
     *     reason_code: ?string,
     *     refund_percent: int,
     *     refund: array{amount: int, currency: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason_code' => $this->reasonCode,
            'refund_percent' => $this->refundPercent,
            'refund' => $this->refund->toArray(),
        ];
    }
}
