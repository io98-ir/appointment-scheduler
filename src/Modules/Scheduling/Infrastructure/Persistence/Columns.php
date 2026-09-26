<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\OwnerType;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\LocalTime;

/**
 * Conversions the scheduling tables share.
 */
final class Columns
{
    /**
     * Tells listeners, such as the availability cache, that a schedule,
     * exception or holiday changed.
     */
    public static function changed(): void
    {
        \do_action(Hooks::name('scheduling/changed'));
    }

    /**
     * A TIME column value: "09:30:00", or "24:00:00" for the end of a day.
     */
    public static function time(LocalTime $time): string
    {
        return $time->toString() . ':00';
    }

    public static function localTime(string $time): LocalTime
    {
        return LocalTime::fromString(\substr($time, 0, 5));
    }

    public static function owner(Row $row): Owner
    {
        return new Owner(OwnerType::from($row->string('owner_type')), $row->int('owner_id'));
    }

    /**
     * The owners' ids by type, one query each (the owner index leads with the type).
     *
     * @param list<Owner> $owners
     * @return array<string, non-empty-list<int>>
     */
    public static function idsByType(array $owners): array
    {
        $byType = [];
        foreach ($owners as $owner) {
            $byType[$owner->type->value][$owner->id] = $owner->id;
        }

        return \array_map(\array_values(...), $byType);
    }

    /**
     * The current time as the DATETIME columns store it (UTC).
     */
    public static function now(Clock $clock): string
    {
        return $clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
