<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Rounding;

/**
 * A discount code, as a coupons row (data-model §2).
 */
final class Coupon
{
    /**
     * @param int $value a percentage (1 to 100) or an amount in rials.
     * @param ?int $validFrom UTC seconds, inclusive.
     * @param ?int $validTo UTC seconds, exclusive.
     * @param ?int $maxUses null is unlimited.
     * @param ?list<int> $serviceIds null is every service.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly CouponType $type,
        public readonly int $value,
        public readonly bool $active,
        public readonly ?int $validFrom = null,
        public readonly ?int $validTo = null,
        public readonly ?int $maxUses = null,
        public readonly int $used = 0,
        public readonly ?array $serviceIds = null,
    ) {
        if (CouponType::Percent === $type ? $value < 1 || $value > 100 : $value < 1) {
            throw new InvalidValue('invalid_coupon_value', 'A coupon takes 1% to 100%, or a positive amount.');
        }
    }

    /**
     * @throws InvalidValue coupon_inactive, coupon_expired,
     *     coupon_not_applicable or coupon_used_up.
     */
    public function assertUsable(int $serviceId, int $now): void
    {
        if (!$this->active) {
            throw new InvalidValue('coupon_inactive', 'The coupon is not active.');
        }
        $early = null !== $this->validFrom && $now < $this->validFrom;
        $late = null !== $this->validTo && $now >= $this->validTo;
        if ($early || $late) {
            throw new InvalidValue('coupon_expired', 'The coupon is not valid now.');
        }
        if (null !== $this->serviceIds && !\in_array($serviceId, $this->serviceIds, true)) {
            throw new InvalidValue('coupon_not_applicable', 'The coupon is not for this service.');
        }
        if (null !== $this->maxUses && $this->used >= $this->maxUses) {
            throw new InvalidValue('coupon_used_up', 'The coupon has been used up.');
        }
    }

    /**
     * The discount on $total, as a positive amount: never more than $total.
     */
    public function discountOn(Money $total, Rounding $rounding): Money
    {
        $discount = CouponType::Percent === $this->type
            ? $total->percent($this->value, $rounding)
            : Money::ofRial($this->value);

        return $discount->isGreaterThan($total) ? $total : $discount;
    }
}
