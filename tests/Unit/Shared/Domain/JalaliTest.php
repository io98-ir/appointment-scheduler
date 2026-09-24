<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared\Domain;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Jalali;
use Vaqtyar\Shared\Domain\LocalDate;

final class JalaliTest extends TestCase
{
    private Jalali $jalali;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jalali = new Jalali();
    }

    /**
     * @return iterable<string, array{string, array{int, int, int}}>
     */
    public static function knownDates(): iterable
    {
        yield 'today in this project' => ['2026-09-24', [1405, 7, 2]];
        yield 'Nowruz 1405' => ['2026-03-21', [1405, 1, 1]];
        yield 'last day of leap 1403' => ['2025-03-20', [1403, 12, 30]];
        yield 'Nowruz 1404' => ['2025-03-21', [1404, 1, 1]];
        yield 'last day of Shahrivar (31 days)' => ['2026-09-22', [1405, 6, 31]];
        yield 'first day of Mehr' => ['2026-09-23', [1405, 7, 1]];
        yield 'start of the tested range' => ['1921-03-21', [1300, 1, 1]];
        yield 'Gregorian leap day' => ['2028-02-29', [1406, 12, 10]];
    }

    /**
     * @dataProvider knownDates
     * @param array{int, int, int} $jalali
     */
    public function testConvertsBothWays(string $gregorian, array $jalali): void
    {
        self::assertSame($jalali, $this->jalali->fromGregorian(LocalDate::fromString($gregorian)));
        self::assertSame($gregorian, $this->jalali->toGregorian(...$jalali)->toString());
    }

    public function testLeapYears(): void
    {
        self::assertTrue($this->jalali->isLeapYear(1399));
        self::assertTrue($this->jalali->isLeapYear(1403));
        self::assertFalse($this->jalali->isLeapYear(1404));
        self::assertFalse($this->jalali->isLeapYear(1405));
    }

    public function testDaysInMonth(): void
    {
        self::assertSame(31, $this->jalali->daysInMonth(1405, 1));
        self::assertSame(31, $this->jalali->daysInMonth(1405, 6));
        self::assertSame(30, $this->jalali->daysInMonth(1405, 7));
        self::assertSame(30, $this->jalali->daysInMonth(1405, 11));
        self::assertSame(29, $this->jalali->daysInMonth(1404, 12));
        self::assertSame(30, $this->jalali->daysInMonth(1403, 12));
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function invalidJalaliDates(): iterable
    {
        yield 'Esfand 30 in a common year' => [1404, 12, 30];
        yield 'Mehr 31' => [1405, 7, 31];
        yield 'month 13' => [1405, 13, 1];
        yield 'month 0' => [1405, 0, 1];
        yield 'day 0' => [1405, 1, 0];
        yield 'year 0' => [0, 1, 1];
        yield 'beyond the supported range' => [3177, 1, 1];
    }

    /**
     * @dataProvider invalidJalaliDates
     */
    public function testRejectsInvalidJalaliDates(int $year, int $month, int $day): void
    {
        try {
            $this->jalali->toGregorian($year, $month, $day);
            self::fail("Accepted $year/$month/$day");
        } catch (InvalidValue $e) {
            self::assertSame('invalid_jalali_date', $e->errorCode);
        }
    }

    public function testDaysInMonthRejectsAnInvalidMonth(): void
    {
        $this->expectException(InvalidValue::class);

        $this->jalali->daysInMonth(1405, 13);
    }

    public function testRejectsGregorianDatesOutsideTheSupportedRange(): void
    {
        $lastDayOfLastYear = $this->jalali->daysInMonth(3176, 12);
        $firstSupported = $this->jalali->toGregorian(1, 1, 1);
        $lastSupported = $this->jalali->toGregorian(3176, 12, $lastDayOfLastYear);
        self::assertSame([1, 1, 1], $this->jalali->fromGregorian($firstSupported));
        self::assertSame([3176, 12, $lastDayOfLastYear], $this->jalali->fromGregorian($lastSupported));

        $outside = [$firstSupported->addDays(-1), $lastSupported->addDays(1), LocalDate::fromString('0500-01-01')];
        foreach ($outside as $date) {
            try {
                $this->jalali->fromGregorian($date);
                self::fail('Converted ' . $date->toString());
            } catch (InvalidValue $e) {
                self::assertSame('invalid_jalali_date', $e->errorCode);
            }
        }
    }

    /**
     * Beyond ICU's agreement (it differs from 1634 on), the calendar must at
     * least be continuous: every year starts the day after the previous one
     * ends, and is 365 or 366 days long as its leap flag says. This covers the
     * algorithm's break-year corrections that 1300–1500 never reaches.
     */
    public function testEveryYearFollowsThePreviousOneWithoutGapsOrOverlaps(): void
    {
        $previousNowruz = $this->jalali->toGregorian(1, 1, 1);
        for ($year = 1; $year <= 3175; ++$year) {
            $nextNowruz = $this->jalali->toGregorian($year + 1, 1, 1);
            $lastDay = $nextNowruz->addDays(-1);

            self::assertSame(
                $this->jalali->isLeapYear($year) ? 366 : 365,
                $previousNowruz->daysUntil($nextNowruz),
                "length of $year"
            );
            $esfandLength = $this->jalali->daysInMonth($year, 12);
            self::assertSame([$year, 12, $esfandLength], $this->jalali->fromGregorian($lastDay));
            self::assertSame([$year + 1, 1, 1], $this->jalali->fromGregorian($nextNowruz));
            $previousNowruz = $nextNowruz;
        }
    }

    /**
     * Every day from 1300/01/01 to the end of 1500 (about 73,000 days) against
     * ICU's Persian calendar, in both directions, plus every year's leap flag.
     */
    public function testAgreesWithIcuFrom1300To1500(): void
    {
        if (!\class_exists(IntlDateFormatter::class)) {
            self::markTestSkipped('The intl extension is not loaded.');
        }
        $icu = new IntlDateFormatter(
            'en_US@calendar=persian',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'UTC',
            IntlDateFormatter::TRADITIONAL,
            'y-M-d'
        );

        $date = LocalDate::fromString('1921-03-21');
        $utc = new DateTimeZone('UTC');
        $mismatches = [];
        do {
            $expected = \array_map('intval', \explode('-', (string) $icu->format($date->startOfDay($utc))));
            $actual = $this->jalali->fromGregorian($date);
            if ($expected !== $actual) {
                $mismatches[] = \sprintf(
                    '%s: ICU %s, ours %s',
                    $date->toString(),
                    \implode('/', $expected),
                    \implode('/', $actual)
                );
            } elseif (!$this->jalali->toGregorian(...$actual)->equals($date)) {
                $mismatches[] = $date->toString() . ': round trip failed';
            }
            $date = $date->addDays(1);
        } while ($actual[0] <= 1500 && \count($mismatches) < 10);

        self::assertSame([], $mismatches);

        for ($year = 1300; $year <= 1500; ++$year) {
            $lastDay = $this->jalali->toGregorian($year + 1, 1, 1)->addDays(-1)->startOfDay($utc);
            $icuEsfandLength = (int) \explode('-', (string) $icu->format($lastDay))[2];
            self::assertSame($icuEsfandLength === 30, $this->jalali->isLeapYear($year), "leap $year");
        }
    }

    public function testConvertsAnInstantInTheSiteZone(): void
    {
        // 21:00 UTC on 23 Sep is already 24 Sep (Mehr 2) in Tehran.
        $date = LocalDate::fromDateTime(new DateTimeImmutable('2026-09-23T21:00:00Z'), new DateTimeZone('Asia/Tehran'));

        self::assertSame([1405, 7, 2], $this->jalali->fromGregorian($date));
    }
}
