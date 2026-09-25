<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * Checks for the ids and sort orders every catalog entity carries. WordPress
 * turns MySQL strict mode off, so a value out of the column's range would be
 * clamped silently (a negative id stored as 0) instead of rejected.
 */
trait GuardsStoredNumbers
{
    private static function assertIds(?int ...$ids): void
    {
        foreach ($ids as $id) {
            if (null !== $id && $id < 1) {
                throw new InvalidValue('invalid_id', 'An id is a positive integer.');
            }
        }
    }

    /**
     * Sort orders come from dragging rows in the admin: 0, 1, 2, …
     * (a trait constant needs PHP 8.2).
     */
    private static function assertSort(int $sort): void
    {
        if ($sort < 0 || $sort > 1_000_000) {
            throw new InvalidValue('invalid_sort', 'A sort order is 0 to 1000000.');
        }
    }
}
