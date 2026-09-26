<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Scheduling\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Scheduling\Domain\DayPlan;
use Vaqtyar\Modules\Scheduling\Domain\ExceptionKind;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\OwnerType;
use Vaqtyar\Modules\Scheduling\Domain\RuleKind;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleException;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;

/**
 * Working = weekly hours − breaks (none on a holiday) + extra hours − time
 * off; blocked time is kept apart, as busy (booking-engine §2).
 */
final class DayPlanTest extends TestCase
{
    // 2026-10-03 is a Saturday (weekday 0), 2026-10-04 a Sunday (1).
    private const SATURDAY = '2026-10-03';

    public function testNoRulesIsAClosedDay(): void
    {
        $plan = DayPlan::of(LocalDate::fromString(self::SATURDAY), [], [], false);

        self::assertSame([[], []], [$plan->working->intervals(), $plan->blocked->intervals()]);
    }

    public function testWeeklyHoursOfThatWeekdayMinusBreaks(): void
    {
        $rules = [
            self::rule(0, '09:00', '13:00'),
            self::rule(0, '14:00', '18:00'),
            self::rule(0, '15:00', '15:30', RuleKind::Break),
            self::rule(1, '08:00', '20:00'),
        ];

        self::assertSame(
            [[540, 780], [840, 900], [930, 1080]],
            DayPlan::of(LocalDate::fromString(self::SATURDAY), $rules, [], false)->working->intervals()
        );
        self::assertSame(
            [[480, 1200]],
            DayPlan::of(LocalDate::fromString('2026-10-04'), $rules, [], false)->working->intervals()
        );
    }

    public function testAHolidayClosesTheWeeklyHoursButNotExtraHours(): void
    {
        $rules = [self::rule(0, '09:00', '17:00')];
        $extra = [self::exception(self::SATURDAY, ExceptionKind::Extra, '10:00', '12:00')];

        self::assertSame(
            [],
            DayPlan::of(LocalDate::fromString(self::SATURDAY), $rules, [], true)->working->intervals()
        );
        self::assertSame(
            [[600, 720]],
            DayPlan::of(LocalDate::fromString(self::SATURDAY), $rules, $extra, true)->working->intervals()
        );
    }

    /**
     * Extra hours are an explicit choice for that date, so the weekly break
     * does not cut into them.
     */
    public function testExtraHoursAddToTheWeekAndIgnoreItsBreaks(): void
    {
        $rules = [
            self::rule(0, '09:00', '13:00'),
            self::rule(0, '12:00', '12:30', RuleKind::Break),
        ];
        $extra = [
            self::exception(self::SATURDAY, ExceptionKind::Extra, '12:00', '14:00'),
            self::exception(self::SATURDAY, ExceptionKind::Extra, '20:00', '24:00'),
        ];

        self::assertSame(
            [[540, 840], [1200, 1440]],
            DayPlan::of(LocalDate::fromString(self::SATURDAY), $rules, $extra, false)->working->intervals()
        );
    }

    public function testTimeOffWinsOverEverything(): void
    {
        $rules = [self::rule(0, '09:00', '17:00')];
        $partial = [
            self::exception(self::SATURDAY, ExceptionKind::Extra, '18:00', '20:00'),
            self::exception(self::SATURDAY, ExceptionKind::Off, '08:00', '10:00'),
            self::exception(self::SATURDAY, ExceptionKind::Off, '19:00', '21:00'),
        ];
        $wholeDay = [
            self::exception(self::SATURDAY, ExceptionKind::Extra, '18:00', '20:00'),
            self::exception(self::SATURDAY, ExceptionKind::Off, null, null),
        ];

        self::assertSame(
            [[600, 1020], [1080, 1140]],
            DayPlan::of(LocalDate::fromString(self::SATURDAY), $rules, $partial, false)->working->intervals()
        );
        self::assertSame(
            [],
            DayPlan::of(LocalDate::fromString(self::SATURDAY), $rules, $wholeDay, false)->working->intervals()
        );
    }

    /**
     * Blocked time stays working time: availability counts it as busy, so a
     * month view can tell "full" from "closed".
     */
    public function testBlockedTimeIsKeptApart(): void
    {
        $rules = [self::rule(0, '09:00', '17:00')];
        $blocked = [
            self::exception(self::SATURDAY, ExceptionKind::Blocked, '10:00', '11:00'),
            self::exception(self::SATURDAY, ExceptionKind::Blocked, '10:30', '12:00'),
        ];
        $wholeDay = [self::exception(self::SATURDAY, ExceptionKind::Blocked, null, null)];

        $plan = DayPlan::of(LocalDate::fromString(self::SATURDAY), $rules, $blocked, false);
        self::assertSame([[[540, 1020]], [[600, 720]]], [$plan->working->intervals(), $plan->blocked->intervals()]);
        self::assertSame(
            [[0, 1440]],
            DayPlan::of(LocalDate::fromString(self::SATURDAY), $rules, $wholeDay, false)->blocked->intervals()
        );
    }

    public function testExceptionsOfOtherDatesAreIgnored(): void
    {
        $rules = [self::rule(0, '09:00', '17:00')];
        $other = [
            self::exception('2026-10-10', ExceptionKind::Off, null, null),
            self::exception('2026-10-04', ExceptionKind::Extra, '18:00', '20:00'),
        ];

        self::assertSame(
            [[540, 1020]],
            DayPlan::of(LocalDate::fromString(self::SATURDAY), $rules, $other, false)->working->intervals()
        );
    }

    /**
     * A plan is one owner's day; mixing owners is a bug in the caller.
     */
    public function testRefusesTheRulesOfSeveralOwners(): void
    {
        $this->expectException(\LogicException::class);

        DayPlan::of(
            LocalDate::fromString(self::SATURDAY),
            [self::rule(0, '09:00', '17:00'), self::rule(0, '09:00', '17:00', RuleKind::Work, 2)],
            [],
            false
        );
    }

    public function testRefusesTheExceptionsOfAnotherOwner(): void
    {
        $this->expectException(\LogicException::class);

        DayPlan::of(
            LocalDate::fromString(self::SATURDAY),
            [self::rule(0, '09:00', '17:00')],
            [self::exception(self::SATURDAY, ExceptionKind::Off, null, null, 2)],
            false
        );
    }

    private static function rule(
        int $weekday,
        string $start,
        string $end,
        RuleKind $kind = RuleKind::Work,
        int $staffId = 1,
    ): ScheduleRule {
        return new ScheduleRule(
            null,
            new Owner(OwnerType::Staff, $staffId),
            $weekday,
            LocalTime::fromString($start),
            LocalTime::fromString($end),
            $kind
        );
    }

    private static function exception(
        string $date,
        ExceptionKind $kind,
        ?string $start,
        ?string $end,
        int $staffId = 1,
    ): ScheduleException {
        return new ScheduleException(
            null,
            new Owner(OwnerType::Staff, $staffId),
            LocalDate::fromString($date),
            null === $start ? null : LocalTime::fromString($start),
            null === $end ? null : LocalTime::fromString($end),
            $kind,
            ''
        );
    }
}
