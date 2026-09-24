<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared\Domain;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;

final class LocalDateTest extends TestCase
{
    public function testParsesAndPrintsIsoDates(): void
    {
        $date = LocalDate::fromString('2026-09-24');

        self::assertSame('2026-09-24', $date->toString());
        self::assertSame(2026, $date->year);
        self::assertSame(9, $date->month);
        self::assertSame(24, $date->day);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDates(): iterable
    {
        yield 'impossible day' => ['2026-02-30'];
        yield 'not a leap year' => ['2025-02-29'];
        yield 'wrong format' => ['24/09/2026'];
        yield 'time included' => ['2026-09-24 10:00'];
        yield 'single digits' => ['2026-9-4'];
        yield 'trailing newline' => ["2026-09-24\n"];
        yield 'empty' => [''];
    }

    /**
     * @dataProvider invalidDates
     */
    public function testRejectsInvalidDates(string $input): void
    {
        $this->expectException(InvalidValue::class);

        LocalDate::fromString($input);
    }

    public function testAcceptsLeapDay(): void
    {
        self::assertSame('2028-02-29', LocalDate::fromString('2028-02-29')->toString());
    }

    public function testTheSameInstantIsADifferentDateInDifferentZones(): void
    {
        $instant = new DateTimeImmutable('2026-09-24T21:00:00Z');

        self::assertSame('2026-09-25', LocalDate::fromDateTime($instant, new DateTimeZone('Asia/Tehran'))->toString());
        self::assertSame('2026-09-24', LocalDate::fromDateTime($instant, new DateTimeZone('UTC'))->toString());
    }

    public function testAddDaysAcrossMonthAndYear(): void
    {
        self::assertSame('2026-10-01', LocalDate::fromString('2026-09-30')->addDays(1)->toString());
        self::assertSame('2027-01-01', LocalDate::fromString('2026-12-31')->addDays(1)->toString());
        self::assertSame('2026-09-01', LocalDate::fromString('2026-09-24')->addDays(-23)->toString());
    }

    public function testIsoDayOfWeek(): void
    {
        self::assertSame(4, LocalDate::fromString('2026-09-24')->dayOfWeek(), 'Thursday');
        self::assertSame(6, LocalDate::fromString('2026-09-26')->dayOfWeek(), 'Saturday');
        self::assertSame(7, LocalDate::fromString('2026-09-27')->dayOfWeek(), 'Sunday');
    }

    public function testOrdersDatesAndCountsDaysBetweenThem(): void
    {
        $a = LocalDate::fromString('2026-09-24');
        $b = LocalDate::fromString('2026-09-25');

        self::assertTrue($a->isBefore($b));
        self::assertFalse($b->isBefore($a));
        self::assertFalse($a->isBefore($a));
        self::assertTrue($a->equals(LocalDate::fromString('2026-09-24')));
        self::assertSame(1, $a->daysUntil($b));
        self::assertSame(-365, $a->daysUntil(LocalDate::fromString('2025-09-24')));
    }

    public function testStartOfDayInAZone(): void
    {
        $start = LocalDate::fromString('2026-09-24')->startOfDay(new DateTimeZone('Asia/Tehran'));

        self::assertSame('2026-09-23T20:30:00+00:00', $start->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM));
    }
}
