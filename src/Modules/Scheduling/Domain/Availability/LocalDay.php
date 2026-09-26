<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain\Availability;

use DateTimeImmutable;
use DateTimeZone;
use Vaqtyar\Shared\Domain\IntervalSet;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * One local date of a location's time zone, as UTC seconds: where the
 * minutes of a DayPlan (wall-clock times, 0 to 1440) fall on the timeline.
 * On a DST day the day is 23 or 25 hours long.
 */
final class LocalDay
{
    public function __construct(public readonly LocalDate $date, public readonly DateTimeZone $zone)
    {
    }

    /**
     * The first second of the date.
     */
    public function start(): int
    {
        return $this->date->startOfDay($this->zone)->getTimestamp();
    }

    /**
     * The first second of the next date.
     */
    public function end(): int
    {
        return $this->date->addDays(1)->startOfDay($this->zone)->getTimestamp();
    }

    /**
     * @param IntervalSet $minutes Minutes of this date, 0 to 1440.
     * @return IntervalSet The same times in UTC seconds.
     */
    public function utc(IntervalSet $minutes): IntervalSet
    {
        return IntervalSet::of(\array_map(
            fn (array $range): array => [$this->instant($range[0]), $this->instant($range[1])],
            $minutes->intervals()
        ));
    }

    /**
     * A wall-clock time that the spring-forward gap skips (02:30 when clocks
     * jump from 02:00 to 03:00) is the transition itself, so a range across
     * the gap keeps its real part and one inside it becomes empty. PHP would
     * push it forward by the gap instead, past later times of the same range.
     * In the repeated hour of autumn, PHP picks the first occurrence.
     */
    private function instant(int $minute): int
    {
        if ($minute >= 1440) {
            return $this->end();
        }
        $wallClock = \sprintf('%02d:%02d', \intdiv($minute, 60), $minute % 60);
        $instant = new DateTimeImmutable($this->date->toString() . ' ' . $wallClock . ':00', $this->zone);
        if ($instant->format('H:i') === $wallClock) {
            return $instant->getTimestamp();
        }

        return $this->transitionBefore($instant->getTimestamp());
    }

    /**
     * The first second with the offset $after has, searched in the day
     * before it: the transition that the skipped wall-clock time falls in.
     * A search on offsets, because getTransitions() lists nothing for
     * recent years with the slim system tzdata that Linux PHP builds read
     * (future rules live in the file's POSIX footer), CI run 36263620890.
     */
    private function transitionBefore(int $after): int
    {
        $offset = $this->offsetAt($after);
        $low = $after - 86_400;
        $high = $after;
        while ($high - $low > 1) {
            $middle = \intdiv($low + $high, 2);
            if ($this->offsetAt($middle) === $offset) {
                $high = $middle;
            } else {
                $low = $middle;
            }
        }

        return $high;
    }

    private function offsetAt(int $timestamp): int
    {
        return $this->zone->getOffset(new DateTimeImmutable('@' . $timestamp));
    }
}
