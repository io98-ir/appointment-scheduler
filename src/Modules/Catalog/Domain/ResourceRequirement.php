<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Slug;

/**
 * A service needs this many resources of a group for each appointment.
 */
final class ResourceRequirement
{
    public const MAX_QUANTITY = 100;

    public function __construct(
        public readonly Slug $groupKey,
        public readonly int $quantity = 1,
    ) {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidValue('invalid_quantity', 'A service needs 1 to 100 resources of a group.');
        }
    }
}
