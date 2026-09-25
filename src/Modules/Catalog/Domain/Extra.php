<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;

/**
 * An add-on a customer picks with a service, adding its price and time per
 * unit. A negative price is refused: discounts are price rules and coupons
 * (booking-engine §5), which show as their own lines.
 */
final class Extra
{
    use GuardsStoredNumbers;

    public const MAX_QTY = 100;

    /**
     * @param ?int $id null until stored.
     * @param ?int $serviceId null for an extra every service offers.
     * @param int $durationMin minutes each unit adds; 0 for one that takes no time.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly Name $name,
        public readonly Money $price,
        public readonly int $durationMin,
        public readonly ?int $serviceId = null,
        public readonly int $maxQty = 1,
        public readonly Status $status = Status::Active,
    ) {
        self::assertIds($id, $serviceId);
        if ($price->isNegative()) {
            throw new InvalidValue('invalid_price', 'A price cannot be negative.');
        }
        if ($durationMin < 0 || $durationMin > Variant::MAX_MINUTES) {
            throw new InvalidValue('invalid_duration', 'An extra adds 0 to 1440 minutes.');
        }
        if ($maxQty < 1 || $maxQty > self::MAX_QTY) {
            throw new InvalidValue('invalid_quantity', 'An extra can be taken 1 to 100 times.');
        }
    }
}
