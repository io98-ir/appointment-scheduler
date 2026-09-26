<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;

/**
 * An extra the customer added, at its price when held.
 */
final class ChosenExtra
{
    public function __construct(
        public readonly int $extraId,
        public readonly Money $unitPrice,
        public readonly int $qty,
    ) {
        if ($qty < 1 || $unitPrice->isNegative()) {
            throw new InvalidValue('invalid_extra', 'An extra is at least one unit, at a price that is not negative.');
        }
    }
}
