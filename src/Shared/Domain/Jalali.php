<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * Converts between the Gregorian and the Jalali (Solar Hijri) calendar.
 *
 * Port of Borkowski's arithmetic as used by jalaali-js: a 33-year leap cycle
 * corrected by break years. Supported years are 1–3176 (the algorithm needs
 * the following year's Nowruz). It agrees with ICU's Persian calendar for
 * 1300–1633 (tested 1300–1500); ICU uses a plain 33-year rule and differs
 * from 1634 on. Dates stay Gregorian everywhere else (LocalDate); Jalali is
 * for input and display only (principles §4).
 */
final class Jalali
{
    private const BREAKS = [
        -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
        1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178,
    ];
    private const MIN_YEAR = 1;
    private const MAX_YEAR = 3176;

    /**
     * @return array{int, int, int} Jalali year, month (1–12) and day.
     */
    public function fromGregorian(LocalDate $date): array
    {
        $dayNumber = self::gregorianToDayNumber($date->year, $date->month, $date->day);
        $year = $date->year - 621;
        // One year past the maximum can still be computed; its first months
        // belong to the maximum year. The result is checked below.
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR + 1) {
            throw self::invalid();
        }
        [$leap, $gregorianYear, $march] = self::calendar($year);
        $sinceNowruz = $dayNumber - self::gregorianToDayNumber($gregorianYear, 3, $march);

        if ($sinceNowruz >= 0) {
            if ($sinceNowruz <= 185) {
                $month = 1 + \intdiv($sinceNowruz, 31);
                $day = $sinceNowruz % 31 + 1;
            } else {
                $sinceNowruz -= 186;
                $month = 7 + \intdiv($sinceNowruz, 30);
                $day = $sinceNowruz % 30 + 1;
            }
        } else {
            // Before this Gregorian year's Nowruz: the end of the previous Jalali
            // year, which was a leap year when this one is 1 year past a leap.
            --$year;
            $sinceNowruz += 179;
            if (1 === $leap) {
                ++$sinceNowruz;
            }
            $month = 7 + \intdiv($sinceNowruz, 30);
            $day = $sinceNowruz % 30 + 1;
        }
        self::assertYear($year);

        return [$year, $month, $day];
    }

    public function toGregorian(int $year, int $month, int $day): LocalDate
    {
        self::assertYear($year);
        if ($month < 1 || $month > 12 || $day < 1 || $day > $this->daysInMonth($year, $month)) {
            throw self::invalid();
        }
        [, $gregorianYear, $march] = self::calendar($year);
        $dayNumber = self::gregorianToDayNumber($gregorianYear, 3, $march)
            + ($month - 1) * 31 - \intdiv($month, 7) * ($month - 7) + $day - 1;

        return LocalDate::fromString(self::dayNumberToGregorian($dayNumber));
    }

    public function isLeapYear(int $year): bool
    {
        self::assertYear($year);

        return 0 === self::calendar($year)[0];
    }

    public function daysInMonth(int $year, int $month): int
    {
        return match (true) {
            $month >= 1 && $month <= 6 => 31,
            $month >= 7 && $month <= 11 => 30,
            12 === $month => $this->isLeapYear($year) ? 30 : 29,
            default => throw self::invalid(),
        };
    }

    /**
     * @return array{int, int, int} Years since the last leap year (0 = leap),
     *     the Gregorian year of Nowruz, and Nowruz's day in March.
     */
    private static function calendar(int $year): array
    {
        $gregorianYear = $year + 621;
        $leapJ = -14;
        $previousBreak = self::BREAKS[0];
        $jump = 0;
        foreach (\array_slice(self::BREAKS, 1) as $break) {
            $jump = $break - $previousBreak;
            if ($year < $break) {
                break;
            }
            $leapJ += \intdiv($jump, 33) * 8 + \intdiv($jump % 33, 4);
            $previousBreak = $break;
        }
        $n = $year - $previousBreak;
        $leapJ += \intdiv($n, 33) * 8 + \intdiv($n % 33 + 3, 4);
        if (4 === $jump % 33 && 4 === $jump - $n) {
            ++$leapJ;
        }
        $leapG = \intdiv($gregorianYear, 4) - \intdiv((\intdiv($gregorianYear, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;
        if ($jump - $n < 6) {
            $n = $n - $jump + \intdiv($jump + 4, 33) * 33;
        }
        $leap = ((($n + 1) % 33) - 1) % 4;

        return [-1 === $leap ? 4 : $leap, $gregorianYear, $march];
    }

    /**
     * Julian Day Number of a Gregorian date.
     */
    private static function gregorianToDayNumber(int $year, int $month, int $day): int
    {
        $shift = \intdiv($month - 8, 6);
        $dayNumber = \intdiv(($year + $shift + 100100) * 1461, 4)
            + \intdiv(153 * (($month + 9) % 12) + 2, 5)
            + $day - 34840408;

        return $dayNumber - \intdiv(\intdiv($year + 100100 + $shift, 100) * 3, 4) + 752;
    }

    /**
     * @return string "YYYY-MM-DD"
     */
    private static function dayNumberToGregorian(int $dayNumber): string
    {
        $j = 4 * $dayNumber + 139361631;
        $j += \intdiv(\intdiv(4 * $dayNumber + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = \intdiv($j % 1461, 4) * 5 + 308;
        $day = \intdiv($i % 153, 5) + 1;
        $month = \intdiv($i, 153) % 12 + 1;
        $year = \intdiv($j, 1461) - 100100 + \intdiv(8 - $month, 6);

        return \sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function assertYear(int $year): void
    {
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
            throw self::invalid();
        }
    }

    private static function invalid(): InvalidValue
    {
        return new InvalidValue('invalid_jalali_date', 'Not a valid Jalali date between years 1 and 3176.');
    }
}
