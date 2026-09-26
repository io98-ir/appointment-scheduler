<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Scheduling\Domain\Availability;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Scheduling\Domain\Availability\LocalDay;
use Vaqtyar\Shared\Domain\IntervalSet;
use Vaqtyar\Shared\Domain\LocalDate;

final class LocalDayTest extends TestCase
{
    /**
     * Tehran is UTC+03:30 all year (no DST since 2023).
     */
    public function testTurnsLocalMinutesIntoUtcSeconds(): void
    {
        $day = new LocalDay(LocalDate::fromString('2026-10-03'), new \DateTimeZone('Asia/Tehran'));
        $utc = $day->utc(IntervalSet::of([[9 * 60, 13 * 60], [16 * 60, 1440]]));

        self::assertSame(
            [['2026-10-03 05:30', '2026-10-03 09:30'], ['2026-10-03 12:30', '2026-10-03 20:30']],
            \array_map(
                static fn (array $r): array => [\gmdate('Y-m-d H:i', $r[0]), \gmdate('Y-m-d H:i', $r[1])],
                $utc->intervals()
            )
        );
        self::assertSame(
            ['2026-10-02 20:30', '2026-10-03 20:30'],
            [\gmdate('Y-m-d H:i', $day->start()), \gmdate('Y-m-d H:i', $day->end())]
        );
    }

    /**
     * Minutes are wall-clock times: on a DST day the day is 23 hours long
     * and 03:00 is one hour after 01:00.
     */
    public function testFollowsTheWallClockOnADstDay(): void
    {
        $day = new LocalDay(LocalDate::fromString('2026-03-29'), new \DateTimeZone('Europe/Berlin'));

        self::assertSame(23 * 3600, $day->end() - $day->start());
        self::assertSame(
            [['2026-03-29 00:00', '2026-03-29 01:00']],
            \array_map(
                static fn (array $r): array => [\gmdate('Y-m-d H:i', $r[0]), \gmdate('Y-m-d H:i', $r[1])],
                $day->utc(IntervalSet::of([[60, 180]]))->intervals()
            )
        );
    }

    /**
     * New York skips 02:00-03:00 on 2026-03-08: a range across the gap keeps
     * its real part, one inside it is gone, and neither throws.
     */
    public function testATimeInTheSpringGapIsTheTransition(): void
    {
        $day = new LocalDay(LocalDate::fromString('2026-03-08'), new \DateTimeZone('America/New_York'));

        self::assertSame(
            [['2026-03-08 06:00', '2026-03-08 07:00'], ['2026-03-08 07:00', '2026-03-08 07:05']],
            [
                self::utcRange($day, [[60, 120]]),
                self::utcRange($day, [[150, 185]]),
            ]
        );
        self::assertSame([], $day->utc(IntervalSet::of([[130, 170]]))->intervals());
        self::assertSame(
            [['2026-03-08 06:00', '2026-03-08 08:00']],
            \array_map(
                static fn (array $r): array => [\gmdate('Y-m-d H:i', $r[0]), \gmdate('Y-m-d H:i', $r[1])],
                $day->utc(IntervalSet::of([[60, 240]]))->intervals()
            )
        );
    }

    /**
     * @param list<array{int, int}> $minutes One range.
     * @return array{string, string}
     */
    private static function utcRange(LocalDay $day, array $minutes): array
    {
        $range = $day->utc(IntervalSet::of($minutes))->intervals()[0];

        return [\gmdate('Y-m-d H:i', $range[0]), \gmdate('Y-m-d H:i', $range[1])];
    }
}
