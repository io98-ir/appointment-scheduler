<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Contracts;

use Vaqtyar\Shared\Domain\Money;

/**
 * An extra the customer may add to a variant of its service, or to any
 * service when it belongs to none.
 */
final class ExtraOffer
{
    /**
     * @param int $durationMin minutes each unit adds.
     * @param Money $price of each unit.
     * @param int $maxQty units one booking may take.
     */
    public function __construct(
        public readonly int $extraId,
        public readonly int $durationMin,
        public readonly Money $price,
        public readonly int $maxQty,
    ) {
    }
}
