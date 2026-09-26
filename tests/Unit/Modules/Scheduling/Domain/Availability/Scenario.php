<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Scheduling\Domain\Availability;

use Vaqtyar\Modules\Scheduling\Domain\Availability\LocalDay;
use Vaqtyar\Modules\Scheduling\Domain\Availability\Occupancy;
use Vaqtyar\Modules\Scheduling\Domain\Availability\Slot;
use Vaqtyar\Shared\Domain\IntervalSet;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * Builds calculator inputs from "HH:MM" times on one UTC day, so a scenario
 * reads like a schedule.
 */
trait Scenario
{
    private static function day(): LocalDay
    {
        return new LocalDay(LocalDate::fromString('2026-10-03'), new \DateTimeZone('UTC'));
    }

    private static function when(string $time): int
    {
        [$h, $m] = \array_map('intval', \explode(':', $time));

        return self::day()->start() + ($h * 60 + $m) * 60;
    }

    /**
     * @param string ...$ranges "09:00-12:00"
     */
    private static function hours(string ...$ranges): IntervalSet
    {
        return IntervalSet::of(\array_map(static function (string $range): array {
            [$from, $to] = \explode('-', $range);

            return [self::when($from), self::when($to)];
        }, $ranges));
    }

    private static function busy(string $range, int $seats = 1, ?int $variantId = null, ?int $staffId = null): Occupancy
    {
        [$from, $to] = \explode('-', $range);

        return new Occupancy(self::when($from), self::when($to), $seats, $variantId, $staffId);
    }

    private static function now(string $time = '00:00', string $date = '2026-10-01'): \DateTimeImmutable
    {
        return new \DateTimeImmutable("{$date} {$time}:00", new \DateTimeZone('UTC'));
    }

    /**
     * @param list<Slot> $slots
     * @return list<string> "09:00 [2,1]": start and the staff in preference order.
     */
    private static function describe(array $slots): array
    {
        return \array_map(static fn (Slot $slot): string => \gmdate('H:i', $slot->start)
            . ' [' . \implode(',', $slot->staffIds()) . ']', $slots);
    }
}
