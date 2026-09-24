<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A calendar date without a time or a time zone (Gregorian; Jalali is only a
 * display concern, handled by the Jalali service).
 */
final class LocalDate
{
    private function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
    ) {
    }

    /**
     * @param string $date "YYYY-MM-DD"
     */
    public static function fromString(string $date): self
    {
        if (
            1 !== \preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m)
            || !\checkdate((int) $m[2], (int) $m[3], (int) $m[1])
        ) {
            throw new InvalidValue('invalid_date', 'Not a valid YYYY-MM-DD date.');
        }

        return new self((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    /**
     * The date that $instant falls on in $zone.
     */
    public static function fromDateTime(DateTimeImmutable $instant, DateTimeZone $zone): self
    {
        return self::fromString($instant->setTimezone($zone)->format('Y-m-d'));
    }

    public function addDays(int $days): self
    {
        return self::fromString($this->utcMidnight()->modify(\sprintf('%+d days', $days))->format('Y-m-d'));
    }

    /**
     * ISO-8601: 1 = Monday … 6 = Saturday, 7 = Sunday.
     */
    public function dayOfWeek(): int
    {
        return (int) $this->utcMidnight()->format('N');
    }

    /**
     * Signed number of days from this date to $other.
     */
    public function daysUntil(self $other): int
    {
        return \intdiv($other->utcMidnight()->getTimestamp() - $this->utcMidnight()->getTimestamp(), 86_400);
    }

    /**
     * The first instant of this date in $zone.
     */
    public function startOfDay(DateTimeZone $zone): DateTimeImmutable
    {
        return new DateTimeImmutable($this->toString() . ' 00:00:00', $zone);
    }

    public function isBefore(self $other): bool
    {
        return $this->toString() < $other->toString();
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    public function toString(): string
    {
        return \sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    private function utcMidnight(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->toString() . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
