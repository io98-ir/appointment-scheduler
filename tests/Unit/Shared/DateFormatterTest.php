<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\Calendar;
use Vaqtyar\Shared\DateFormatter;
use Vaqtyar\Shared\Domain\Digits;
use Vaqtyar\Shared\Domain\Jalali;

final class DateFormatterTest extends TestCase
{
    private DateTimeImmutable $instant;
    private DateTimeZone $tehran;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        // Returns the source text, as WordPress does without a translation file.
        Functions\stubTranslationFunctions();
        Functions\when('wp_date')->alias(
            static fn (string $format, int $timestamp, DateTimeZone $zone): string
                => (new DateTimeImmutable('@' . $timestamp))->setTimezone($zone)->format($format)
        );

        // 24 Sep 2026 = 2 Mehr 1405, 09:05 in Tehran.
        $this->instant = new DateTimeImmutable('2026-09-24T05:35:00Z');
        $this->tehran = new DateTimeZone('Asia/Tehran');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testJalaliWithPersianDigits(): void
    {
        $formatter = new DateFormatter(new Jalali(), Calendar::Jalali, Digits::Persian);

        self::assertSame('۱۴۰۵/۰۷/۰۲', $formatter->date($this->instant, $this->tehran));
        self::assertSame('۲ Mehr ۱۴۰۵', $formatter->longDate($this->instant, $this->tehran));
        self::assertSame('۰۹:۰۵', $formatter->time($this->instant, $this->tehran));
        self::assertSame('۱۴۰۵/۰۷/۰۲ ۰۹:۰۵', $formatter->dateTime($this->instant, $this->tehran));
    }

    public function testJalaliWithLatinDigits(): void
    {
        $formatter = new DateFormatter(new Jalali(), Calendar::Jalali, Digits::Latin);

        self::assertSame('1405/07/02', $formatter->date($this->instant, $this->tehran));
        self::assertSame('2 Mehr 1405', $formatter->longDate($this->instant, $this->tehran));
    }

    public function testGregorian(): void
    {
        $formatter = new DateFormatter(new Jalali(), Calendar::Gregorian, Digits::Latin);

        self::assertSame('2026-09-24', $formatter->date($this->instant, $this->tehran));
        self::assertSame('24 September 2026', $formatter->longDate($this->instant, $this->tehran));
        self::assertSame('09:05', $formatter->time($this->instant, $this->tehran));
    }

    public function testGregorianWithPersianDigits(): void
    {
        $formatter = new DateFormatter(new Jalali(), Calendar::Gregorian, Digits::Persian);

        // "/" rather than "-": with Persian digits a hyphenated date displays reversed.
        self::assertSame('۲۰۲۶/۰۹/۲۴', $formatter->date($this->instant, $this->tehran));
    }

    public function testTheDateDependsOnTheZone(): void
    {
        $formatter = new DateFormatter(new Jalali(), Calendar::Jalali, Digits::Latin);
        $lateEvening = new DateTimeImmutable('2026-09-23T21:00:00Z');

        self::assertSame('1405/07/01', $formatter->date($lateEvening, new DateTimeZone('UTC')));
        self::assertSame('1405/07/02', $formatter->date($lateEvening, $this->tehran));
    }

    public function testEveryJalaliMonthHasAName(): void
    {
        $formatter = new DateFormatter(new Jalali(), Calendar::Jalali, Digits::Latin);
        // The 1st of every month of 1405.
        $firstDays = [
            '2026-03-21', '2026-04-21', '2026-05-22', '2026-06-22', '2026-07-23', '2026-08-23',
            '2026-09-23', '2026-10-23', '2026-11-22', '2026-12-22', '2027-01-21', '2027-02-20',
        ];
        $names = [];
        foreach ($firstDays as $day) {
            $longDate = $formatter->longDate(new DateTimeImmutable($day . 'T12:00:00Z'), $this->tehran);
            $names[] = \explode(' ', $longDate)[1];
        }

        self::assertSame(
            ['Farvardin', 'Ordibehesht', 'Khordad', 'Tir', 'Mordad', 'Shahrivar',
                'Mehr', 'Aban', 'Azar', 'Dey', 'Bahman', 'Esfand'],
            $names
        );
    }

    public function testDigitsReplacesOnlyDigits(): void
    {
        $persian = new DateFormatter(new Jalali(), Calendar::Jalali, Digits::Persian);
        $latin = new DateFormatter(new Jalali(), Calendar::Jalali, Digits::Latin);

        self::assertSame('ساعت ۱۰:۳۰ - ۲۰۲۶', $persian->digits('ساعت 10:30 - 2026'));
        self::assertSame('ساعت 10:30', $latin->digits('ساعت 10:30'));
    }
}
