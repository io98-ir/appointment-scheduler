<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain;

use Vaqtyar\Shared\Domain\IntervalSet;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * One owner's hours on one local date, in minutes of that day (0 to 1440):
 *
 *     working = (weekly work − weekly breaks, or nothing on a holiday)
 *               ∪ extra hours − time off
 *     blocked = blocked time
 *
 * Extra hours are an explicit choice for the date, so neither a holiday nor
 * a weekly break cuts into them; time off wins over everything. Blocked time
 * is busy, not closed (booking-engine §2), so a month view can tell a full
 * day from a closed one.
 */
final class DayPlan
{
    private const WHOLE_DAY = [0, 1440];

    private function __construct(public readonly IntervalSet $working, public readonly IntervalSet $blocked)
    {
    }

    /**
     * @param list<ScheduleRule>      $rules      The owner's weekly schedule; other weekdays are skipped.
     * @param list<ScheduleException> $exceptions The owner's exceptions; other dates are skipped.
     * @param bool                    $holiday    Whether the date is a holiday in the owner's calendar.
     */
    public static function of(LocalDate $date, array $rules, array $exceptions, bool $holiday): self
    {
        self::assertOneOwner($rules, $exceptions);

        $weekday = ScheduleRule::weekdayOf($date);
        $work = [];
        $breaks = [];
        foreach ($rules as $rule) {
            if ($rule->weekday !== $weekday) {
                continue;
            }
            $range = [$rule->start->minutes, $rule->end->minutes];
            if (RuleKind::Work === $rule->kind) {
                $work[] = $range;
            } else {
                $breaks[] = $range;
            }
        }

        $byKind = ['off' => [], 'extra' => [], 'blocked' => []];
        foreach ($exceptions as $exception) {
            if ($exception->date->equals($date)) {
                $byKind[$exception->kind->value][] = null === $exception->start || null === $exception->end
                    ? self::WHOLE_DAY
                    : [$exception->start->minutes, $exception->end->minutes];
            }
        }

        $weekly = $holiday ? IntervalSet::empty() : IntervalSet::of($work)->subtract(IntervalSet::of($breaks));

        return new self(
            $weekly->union(IntervalSet::of($byKind['extra']))->subtract(IntervalSet::of($byKind['off'])),
            IntervalSet::of($byKind['blocked'])
        );
    }

    /**
     * @param list<ScheduleRule>      $rules
     * @param list<ScheduleException> $exceptions
     */
    private static function assertOneOwner(array $rules, array $exceptions): void
    {
        $owner = null;
        foreach ([...$rules, ...$exceptions] as $item) {
            $owner ??= $item->owner;
            if (!$item->owner->equals($owner)) {
                throw new \LogicException('A day plan is for the rules and exceptions of one owner.');
            }
        }
    }
}
