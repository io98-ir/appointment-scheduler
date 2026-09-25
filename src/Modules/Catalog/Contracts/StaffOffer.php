<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Contracts;

use Vaqtyar\Shared\Domain\Money;

/**
 * A staff member serving a variant, with the duration and price that apply
 * to them (Service::terms()), before extras, price rules and coupons.
 */
final class StaffOffer
{
    /**
     * @param ?int $locationId their location; null serves every location.
     */
    public function __construct(
        public readonly int $staffId,
        public readonly ?int $locationId,
        public readonly int $durationMin,
        public readonly Money $price,
    ) {
    }
}
