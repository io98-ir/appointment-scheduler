<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain;

/**
 * The key of a staff member's or resource unit's one calendar, in
 * occupancies.lock_key and resource_day_locks (data-model §2).
 */
final class LockKey
{
    public static function staff(int $id): string
    {
        return 'staff:' . $id;
    }

    public static function resource(int $id): string
    {
        return 'res:' . $id;
    }

    /**
     * Unique and sorted: every writer takes locks in this order (ADR-004).
     *
     * @param list<int> $staffIds
     * @param list<int> $resourceIds
     * @return list<string>
     */
    public static function sorted(array $staffIds, array $resourceIds): array
    {
        $keys = \array_unique([
            ...\array_map(self::staff(...), $staffIds),
            ...\array_map(self::resource(...), $resourceIds),
        ]);
        \sort($keys, \SORT_STRING);

        return $keys;
    }
}
