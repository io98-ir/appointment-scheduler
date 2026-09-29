<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Pricing\Coupon;
use Vaqtyar\Modules\Booking\Domain\Pricing\CouponRepository;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\NotFound;

/**
 * The admin's coupon use cases (T3.5): CRUD on the coupons table. The same
 * capability as booking, not a new one. `used` is never written from here:
 * only BookingService::confirm counts a use.
 */
final class CouponAdminService
{
    public const CAPABILITY = BookingService::CAPABILITY;
    public const MAX_CODE_LENGTH = 64;

    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly CatalogApi $catalog,
        private readonly CouponRepository $coupons,
    ) {
    }

    /**
     * @return list<Coupon>
     */
    public function all(): array
    {
        $this->authorize();

        return $this->coupons->all();
    }

    /**
     * @throws InvalidValue invalid_coupon on a bad code, window, limit or service list.
     * @throws NotFound coupon_not_found for an unknown id, or service_not_found for an unknown service.
     * @throws Conflict coupon_code_taken when another coupon has the code.
     */
    public function save(Coupon $coupon): Coupon
    {
        $this->authorize();
        self::assertValid($coupon);
        foreach ($coupon->serviceIds ?? [] as $serviceId) {
            if (!$this->catalog->isStored('service', $serviceId)) {
                throw new NotFound('service_not_found', 'No service has this id.');
            }
        }
        if (0 !== $coupon->id) {
            $this->coupons->find($coupon->id) ?? throw self::notFound();
        }
        $same = $this->coupons->findByCode($coupon->code);
        if (null !== $same && $same->id !== $coupon->id) {
            throw new Conflict('coupon_code_taken', 'Another coupon has this code.');
        }

        return $this->coupons->save($coupon);
    }

    public function delete(int $id): void
    {
        $this->authorize();
        $this->coupons->find($id) ?? throw self::notFound();
        $this->coupons->delete($id);
    }

    private static function assertValid(Coupon $coupon): void
    {
        if ('' === $coupon->code || \mb_strlen($coupon->code) > self::MAX_CODE_LENGTH) {
            throw new InvalidValue('invalid_coupon', 'A coupon code is 1 to 64 characters.');
        }
        if (null !== $coupon->validFrom && null !== $coupon->validTo && $coupon->validTo <= $coupon->validFrom) {
            throw new InvalidValue('invalid_coupon', 'A coupon ends after it starts.');
        }
        if (null !== $coupon->maxUses && $coupon->maxUses < 1) {
            throw new InvalidValue('invalid_coupon', 'A coupon limit is at least 1 use.');
        }
        if ([] === $coupon->serviceIds) {
            throw new InvalidValue('invalid_coupon', 'A coupon for no services never applies: leave them all instead.');
        }
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }

    private static function notFound(): NotFound
    {
        return new NotFound('coupon_not_found', 'No coupon has this id.');
    }
}
