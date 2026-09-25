<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;

/**
 * A staff member's assignment to a service, optionally with their own price
 * or duration. With no variant it covers every variant of the service; with
 * one, only that variant. How the two combine is Service::terms().
 */
final class ServiceStaff
{
    use GuardsStoredNumbers;

    /**
     * @param ?Money $price null keeps the variant's price.
     * @param ?int $durationMin null keeps the variant's duration.
     */
    public function __construct(
        public readonly int $staffId,
        public readonly ?int $variantId = null,
        public readonly ?Money $price = null,
        public readonly ?int $durationMin = null,
    ) {
        self::assertIds($staffId, $variantId);
        if (null !== $price && $price->isNegative()) {
            throw new InvalidValue('invalid_price', 'A price cannot be negative.');
        }
        if (null !== $durationMin && ($durationMin < 1 || $durationMin > Variant::MAX_MINUTES)) {
            throw new InvalidValue('invalid_duration', 'A duration is 1 to 1440 minutes.');
        }
    }
}
