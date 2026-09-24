<?php

declare(strict_types=1);

namespace Vaqtyar\Shared;

use DateTimeImmutable;
use DateTimeZone;
use Vaqtyar\Shared\Domain\Jalali;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * The one place dates and times become text (principles §9). Instants are
 * stored in UTC; the zone to show them in is always passed explicitly (the
 * site's or the location's).
 *
 * Numeric dates use "/" with Persian digits: Persian digits are bidi class
 * AN, which "/" joins into one run but "-" does not ("۲۰۲۶-۰۹-۲۴" would
 * display reversed). Rendering a date and a time side by side in an LTR
 * page with Persian digits is left to the UI (e.g. <bdi>).
 */
final class DateFormatter
{
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    public function __construct(
        private readonly Jalali $jalali,
        private readonly Calendar $calendar,
        private readonly Digits $digits,
    ) {
    }

    /**
     * Numeric date: 1405/07/02; Gregorian 2026-09-24, or 2026/09/24 with Persian digits.
     */
    public function date(DateTimeImmutable $instant, DateTimeZone $zone): string
    {
        if (Calendar::Gregorian === $this->calendar) {
            $format = Digits::Persian === $this->digits ? 'Y/m/d' : 'Y-m-d';

            return $this->digits($instant->setTimezone($zone)->format($format));
        }
        [$year, $month, $day] = $this->jalali->fromGregorian(LocalDate::fromDateTime($instant, $zone));

        return $this->digits(\sprintf('%04d/%02d/%02d', $year, $month, $day));
    }

    /**
     * Date with the month name: 2 Mehr 1405 (translated), or WordPress's own
     * localised "j F Y" in the Gregorian calendar.
     */
    public function longDate(DateTimeImmutable $instant, DateTimeZone $zone): string
    {
        if (Calendar::Gregorian === $this->calendar) {
            $text = \wp_date('j F Y', $instant->getTimestamp(), $zone);
            if (false === $text) {
                // Only for a non-numeric timestamp, which an int never is.
                throw new \LogicException('wp_date() rejected a timestamp.');
            }

            return $this->digits($text);
        }
        [$year, $month, $day] = $this->jalali->fromGregorian(LocalDate::fromDateTime($instant, $zone));

        return $this->digits(\sprintf('%d %s %d', $day, self::jalaliMonthName($month), $year));
    }

    /**
     * 24-hour time: 09:05.
     */
    public function time(DateTimeImmutable $instant, DateTimeZone $zone): string
    {
        return $this->digits($instant->setTimezone($zone)->format('H:i'));
    }

    public function dateTime(DateTimeImmutable $instant, DateTimeZone $zone): string
    {
        return $this->date($instant, $zone) . ' ' . $this->time($instant, $zone);
    }

    /**
     * Replaces 0–9 in $text with the configured digits.
     */
    public function digits(string $text): string
    {
        return Digits::Persian === $this->digits ? \str_replace(\range('0', '9'), self::PERSIAN_DIGITS, $text) : $text;
    }

    private static function jalaliMonthName(int $month): string
    {
        // Literal calls, one per name, so that the translation tools find them.
        return match ($month) {
            1 => \_x('Farvardin', 'Jalali month', 'vaqtyar'),
            2 => \_x('Ordibehesht', 'Jalali month', 'vaqtyar'),
            3 => \_x('Khordad', 'Jalali month', 'vaqtyar'),
            4 => \_x('Tir', 'Jalali month', 'vaqtyar'),
            5 => \_x('Mordad', 'Jalali month', 'vaqtyar'),
            6 => \_x('Shahrivar', 'Jalali month', 'vaqtyar'),
            7 => \_x('Mehr', 'Jalali month', 'vaqtyar'),
            8 => \_x('Aban', 'Jalali month', 'vaqtyar'),
            9 => \_x('Azar', 'Jalali month', 'vaqtyar'),
            10 => \_x('Dey', 'Jalali month', 'vaqtyar'),
            11 => \_x('Bahman', 'Jalali month', 'vaqtyar'),
            default => \_x('Esfand', 'Jalali month', 'vaqtyar'),
        };
    }
}
