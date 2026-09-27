<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Pricing\Coupon;
use Vaqtyar\Modules\Booking\Domain\Pricing\TimeRule;

/**
 * What prices read besides the catalog: the price rules and the coupons
 * (data-model §2), and a coupon's uses, which confirming a booking counts.
 */
interface PricingReader
{
    /**
     * The service's own and the global time rules, highest priority first.
     *
     * @return list<TimeRule>
     */
    public function timeRules(int $serviceId): array;

    /**
     * The coupon with this code, whatever its state; null when there is none.
     */
    public function coupon(string $code): ?Coupon;

    /**
     * The coupon with this id, locked until the transaction ends, so two
     * bookings cannot both take its last use; null when there is none.
     */
    public function couponForUse(int $id): ?Coupon;

    /**
     * Counts one more use of the coupon.
     */
    public function countUse(int $id): void;
}
