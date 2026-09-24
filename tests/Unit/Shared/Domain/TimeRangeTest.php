<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\TimeRange;

final class TimeRangeTest extends TestCase
{
    public function testIsHalfOpenAndStoredInUtc(): void
    {
        $range = TimeRange::of(
            new DateTimeImmutable('2026-09-24T10:00:00+03:30'),
            new DateTimeImmutable('2026-09-24T11:00:00+03:30')
        );

        self::assertSame('2026-09-24T06:30:00+00:00', $range->start->format(DATE_ATOM));
        self::assertSame('2026-09-24T07:30:00+00:00', $range->end->format(DATE_ATOM));
        self::assertSame(60, $range->minutes());
    }

    public function testRejectsAnEmptyOrReversedRange(): void
    {
        $t = new DateTimeImmutable('2026-09-24T10:00:00Z');

        foreach ([[$t, $t], [$t, $t->modify('-1 minute')]] as [$start, $end]) {
            try {
                TimeRange::of($start, $end);
                self::fail('Accepted an empty or reversed range.');
            } catch (InvalidValue $e) {
                self::assertSame('invalid_time_range', $e->errorCode);
            }
        }
    }

    public function testOverlapsIsFalseForTouchingRanges(): void
    {
        $morning = self::range('10:00', '11:00');

        self::assertTrue($morning->overlaps(self::range('10:30', '11:30')));
        self::assertTrue($morning->overlaps(self::range('09:00', '12:00')));
        self::assertFalse($morning->overlaps(self::range('11:00', '12:00')), 'Back-to-back appointments do not clash.');
        self::assertFalse($morning->overlaps(self::range('09:00', '10:00')));
    }

    public function testContainsTheStartButNotTheEnd(): void
    {
        $range = self::range('10:00', '11:00');

        self::assertTrue($range->contains(new DateTimeImmutable('2026-09-24T10:00:00Z')));
        self::assertFalse($range->contains(new DateTimeImmutable('2026-09-24T11:00:00Z')), 'End is exclusive.');
    }

    public function testKeepsSubSecondPrecisionOut(): void
    {
        $range = TimeRange::of(
            new DateTimeImmutable('2026-09-24T10:00:00.750Z'),
            new DateTimeImmutable('2026-09-24T10:30:00.250Z')
        );

        self::assertSame('10:00:00.000000', $range->start->format('H:i:s.u'));
        self::assertSame(30, $range->minutes());
    }

    public function testRangesWithTheSameBoundsAreEqual(): void
    {
        self::assertTrue(self::range('10:00', '11:00')->equals(self::range('10:00', '11:00')));
        self::assertFalse(self::range('10:00', '11:00')->equals(self::range('10:00', '11:30')));
    }

    private static function range(string $start, string $end): TimeRange
    {
        return TimeRange::of(
            new DateTimeImmutable("2026-09-24T{$start}:00Z"),
            new DateTimeImmutable("2026-09-24T{$end}:00Z")
        );
    }
}
