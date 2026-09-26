<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Scheduling\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Scheduling\Domain\ExceptionKind;
use Vaqtyar\Modules\Scheduling\Domain\Holiday;
use Vaqtyar\Modules\Scheduling\Domain\HolidaySource;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\OwnerType;
use Vaqtyar\Modules\Scheduling\Domain\RuleKind;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleException;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\Slug;
use Vaqtyar\Tests\Unit\Modules\Catalog\Domain\AssertsInvalidValue;

final class EntitiesTest extends TestCase
{
    use AssertsInvalidValue;

    public function testOwnerNeedsAPositiveId(): void
    {
        $owner = new Owner(OwnerType::Staff, 7);

        self::assertSame(['staff', 7], [$owner->type->value, $owner->id]);
        self::assertTrue($owner->equals(new Owner(OwnerType::Staff, 7)));
        self::assertFalse($owner->equals(new Owner(OwnerType::Resource, 7)));
        self::assertFalse($owner->equals(new Owner(OwnerType::Staff, 8)));
        self::assertInvalid('invalid_id', static fn () => new Owner(OwnerType::Staff, 0));
    }

    /**
     * The week starts on Saturday (data-model: weekday 0 = Saturday).
     */
    public function testWeekdayOfADateCountsFromSaturday(): void
    {
        $weekdays = [];
        // 2026-09-26 is a Saturday.
        for ($i = 0; $i < 7; ++$i) {
            $weekdays[] = ScheduleRule::weekdayOf(LocalDate::fromString('2026-09-26')->addDays($i));
        }

        self::assertSame([0, 1, 2, 3, 4, 5, 6], $weekdays);
    }

    public function testRuleIsOneRangeOnOneWeekday(): void
    {
        $rule = self::rule(6, '16:00', '24:00');

        self::assertSame([6, 960, 1440, 'work'], [
            $rule->weekday,
            $rule->start->minutes,
            $rule->end->minutes,
            $rule->kind->value,
        ]);
        self::assertInvalid('invalid_weekday', static fn () => self::rule(7, '09:00', '17:00'));
        self::assertInvalid('invalid_weekday', static fn () => self::rule(-1, '09:00', '17:00'));
        self::assertInvalid('invalid_time_range', static fn () => self::rule(0, '17:00', '09:00'));
        self::assertInvalid('invalid_time_range', static fn () => self::rule(0, '09:00', '09:00'));
        self::assertInvalid('invalid_id', static fn () => new ScheduleRule(
            0,
            new Owner(OwnerType::Staff, 1),
            0,
            LocalTime::fromString('09:00'),
            LocalTime::fromString('17:00'),
            RuleKind::Work
        ));
    }

    public function testExceptionIsAWholeDayOrARange(): void
    {
        $day = self::exception(ExceptionKind::Off, null, null);
        $hours = self::exception(ExceptionKind::Blocked, '10:00', '11:30');

        self::assertTrue($day->isWholeDay());
        self::assertFalse($hours->isWholeDay());
        self::assertSame([600, 690], [$hours->start?->minutes, $hours->end?->minutes]);
        self::assertInvalid('invalid_time_range', static fn () => self::exception(ExceptionKind::Off, '10:00', null));
        self::assertInvalid('invalid_time_range', static fn () => self::exception(ExceptionKind::Off, null, '10:00'));
        self::assertInvalid(
            'invalid_time_range',
            static fn () => self::exception(ExceptionKind::Off, '11:00', '10:00')
        );
    }

    /**
     * "Work all day" has no hours to offer: extra hours are always a range.
     */
    public function testExtraHoursNeedARange(): void
    {
        self::assertInvalid('extra_needs_hours', static fn () => self::exception(ExceptionKind::Extra, null, null));
        self::assertFalse(self::exception(ExceptionKind::Extra, '18:00', '20:00')->isWholeDay());
    }

    public function testExceptionNoteFitsATextColumn(): void
    {
        self::assertSame("Seminar\nroom 2", self::exception(ExceptionKind::Off, null, null, "Seminar\nroom 2")->note);
        self::assertInvalid(
            'invalid_note',
            static fn () => self::exception(ExceptionKind::Off, null, null, \str_repeat('a', 65_536))
        );
        self::assertInvalid(
            'invalid_note',
            static fn () => self::exception(ExceptionKind::Off, null, null, "\xC3\x28")
        );
    }

    public function testHolidayKeepsItsCalendarDateAndTitle(): void
    {
        $holiday = new Holiday(
            Slug::fromInput('ir'),
            LocalDate::fromString('2026-03-21'),
            Name::fromInput('Nowruz'),
            HolidaySource::Dataset
        );

        self::assertSame(['ir', '2026-03-21', 'Nowruz', 'dataset'], [
            $holiday->calendar->value,
            $holiday->date->toString(),
            $holiday->title->value,
            $holiday->source->value,
        ]);
    }

    private static function rule(int $weekday, string $start, string $end): ScheduleRule
    {
        return new ScheduleRule(
            null,
            new Owner(OwnerType::Staff, 1),
            $weekday,
            LocalTime::fromString($start),
            LocalTime::fromString($end),
            RuleKind::Work
        );
    }

    private static function exception(
        ExceptionKind $kind,
        ?string $start,
        ?string $end,
        string $note = '',
    ): ScheduleException {
        return new ScheduleException(
            null,
            new Owner(OwnerType::Staff, 1),
            LocalDate::fromString('2026-10-01'),
            null === $start ? null : LocalTime::fromString($start),
            null === $end ? null : LocalTime::fromString($end),
            $kind,
            $note
        );
    }
}
