<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

use DateTimeZone;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;

/**
 * What a price depends on: the booking as held, and the time it is priced.
 */
final class PriceContext
{
    /**
     * @param Money $basePrice the variant's price, or the staff member's own
     *     for it (Catalog resolves the override: Service::terms()).
     * @param int $start UTC seconds; time rules match it in $zone.
     * @param list<ChosenExtra> $extras
     * @param int $now UTC seconds, for the coupon's validity.
     */
    public function __construct(
        public readonly int $serviceId,
        public readonly Money $basePrice,
        public readonly int $start,
        public readonly DateTimeZone $zone,
        public readonly array $extras,
        public readonly int $partySize,
        public readonly int $now,
        public readonly ?Coupon $coupon = null,
    ) {
        if ($basePrice->isNegative()) {
            throw new InvalidValue('invalid_price', 'A price is not negative.');
        }
        if ($partySize < 1) {
            throw new InvalidValue('invalid_party_size', 'A party is at least one person.');
        }
    }
}
