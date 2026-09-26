<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Pricing\Coupon;
use Vaqtyar\Modules\Booking\Domain\Pricing\TimeRule;

/**
 * What prices read besides the catalog: the price rules and the coupons
 * (data-model §2).
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
}
