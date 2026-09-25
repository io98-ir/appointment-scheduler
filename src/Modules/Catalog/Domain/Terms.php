<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\Money;

/**
 * What a variant takes and costs with a given staff member, before extras,
 * price rules and coupons (booking-engine §5 steps 1 and 2).
 */
final class Terms
{
    public function __construct(
        public readonly int $durationMin,
        public readonly Money $price,
    ) {
    }
}
