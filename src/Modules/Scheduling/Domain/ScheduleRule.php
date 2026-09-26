<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;

/**
 * One range of an owner's weekly schedule: hours of work, or a break inside
 * them. A shift past midnight is two rules, "22:00-24:00" and "00:00-02:00"
 * on the next weekday.
 */
final class ScheduleRule
{
    /**
     * @param int $weekday 0 = Saturday … 6 = Friday (weekdayOf())
     */
    public function __construct(
        public readonly ?int $id,
        public readonly Owner $owner,
        public readonly int $weekday,
        public readonly LocalTime $start,
        public readonly LocalTime $end,
        public readonly RuleKind $kind,
    ) {
        if (null !== $id && $id < 1) {
            throw new InvalidValue('invalid_id', 'An id is a positive integer.');
        }
        if ($weekday < 0 || $weekday > 6) {
            throw new InvalidValue('invalid_weekday', 'A weekday is 0 (Saturday) to 6 (Friday).');
        }
        if (!$start->isBefore($end)) {
            throw new InvalidValue('invalid_time_range', 'A time range must end after it starts.');
        }
    }

    /**
     * The Iranian week starts on Saturday, and the stored weekday counts
     * from it (data-model §2), unlike ISO's Monday.
     */
    public static function weekdayOf(LocalDate $date): int
    {
        return ($date->dayOfWeek() + 1) % 7;
    }
}
