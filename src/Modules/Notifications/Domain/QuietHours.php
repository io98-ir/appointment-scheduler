<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Domain;

/**
 * A daily window in which reminders are held back, in minutes after
 * midnight of the appointment's own timezone. The window may cross midnight
 * (22:00 to 08:00); equal ends mean none.
 */
final class QuietHours
{
    public const MINUTES_PER_DAY = 1440;

    public function __construct(public readonly int $fromMin, public readonly int $toMin)
    {
        foreach ([$fromMin, $toMin] as $minute) {
            if ($minute < 0 || $minute >= self::MINUTES_PER_DAY) {
                throw new \InvalidArgumentException('A minute of the day is 0 to 1439.');
            }
        }
    }

    /**
     * When the window that holds $at ends, in UTC seconds; null when $at is
     * outside every window.
     */
    public function endsAfter(int $at, \DateTimeZone $zone): ?int
    {
        if ($this->fromMin === $this->toMin) {
            return null;
        }
        $local = (new \DateTimeImmutable('@' . $at))->setTimezone($zone);
        $minute = (int) $local->format('G') * 60 + (int) $local->format('i');
        $crosses = $this->fromMin > $this->toMin;
        $inside = $crosses
            ? $minute >= $this->fromMin || $minute < $this->toMin
            : $minute >= $this->fromMin && $minute < $this->toMin;
        if (!$inside) {
            return null;
        }
        // Today's end, or tomorrow's when the window began yesterday evening.
        $hour = \intdiv($this->toMin, 60);
        $minuteOfHour = $this->toMin % 60;
        $end = $local->setTime($hour, $minuteOfHour);
        if ($end <= $local) {
            $end = $end->modify('+1 day')->setTime($hour, $minuteOfHour);
        }

        return $end->getTimestamp();
    }
}
