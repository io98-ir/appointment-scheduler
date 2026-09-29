<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

use Vaqtyar\Shared\Domain\Conflict;

/**
 * The coupons table for the admin CRUD screen (T3.5): flat, by row id, not
 * the booking-time read path (PricingReader), which locks and counts uses.
 */
interface CouponRepository
{
    /**
     * @return list<Coupon> newest first.
     */
    public function all(): array;

    public function find(int $id): ?Coupon;

    public function findByCode(string $code): ?Coupon;

    /**
     * Inserts when $coupon->id is 0, else replaces the stored row. `used` is
     * never written: only a booking counts a use.
     *
     * @throws Conflict coupon_code_taken when another coupon has the code.
     */
    public function save(Coupon $coupon): Coupon;

    public function delete(int $id): void;
}
