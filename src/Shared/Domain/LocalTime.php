<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * A wall-clock time of day in minutes, without a date or a time zone. 24:00 is
 * allowed as the end of a day ("18:00-24:00").
 */
final class LocalTime
{
    private const END_OF_DAY = 1440;

    private function __construct(public readonly int $minutes)
    {
    }

    /**
     * @param string $time "HH:MM", 00:00 to 24:00
     */
    public static function fromString(string $time): self
    {
        if (1 !== \preg_match('/^(\d{2}):([0-5]\d)$/D', $time, $m)) {
            throw self::invalid();
        }

        return self::fromMinutes((int) $m[1] * 60 + (int) $m[2]);
    }

    public static function fromMinutes(int $minutes): self
    {
        if ($minutes < 0 || $minutes > self::END_OF_DAY) {
            throw self::invalid();
        }

        return new self($minutes);
    }

    public function isBefore(self $other): bool
    {
        return $this->minutes < $other->minutes;
    }

    public function equals(self $other): bool
    {
        return $this->minutes === $other->minutes;
    }

    public function toString(): string
    {
        return \sprintf('%02d:%02d', \intdiv($this->minutes, 60), $this->minutes % 60);
    }

    private static function invalid(): InvalidValue
    {
        return new InvalidValue('invalid_time', 'Not a valid HH:MM time between 00:00 and 24:00.');
    }
}
