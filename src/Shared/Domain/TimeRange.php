<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

use DateTimeImmutable;

/**
 * A non-empty half-open span of time [start, end), in UTC and whole seconds.
 * Back-to-back ranges therefore never overlap.
 */
final class TimeRange
{
    private function __construct(
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
    ) {
    }

    public static function of(DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        // "@<timestamp>" is UTC and drops the fraction of a second.
        $start = new DateTimeImmutable('@' . $start->getTimestamp());
        $end = new DateTimeImmutable('@' . $end->getTimestamp());
        if ($start >= $end) {
            throw new InvalidValue('invalid_time_range', 'A time range must end after it starts.');
        }

        return new self($start, $end);
    }

    public function minutes(): int
    {
        return \intdiv($this->end->getTimestamp() - $this->start->getTimestamp(), 60);
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    public function contains(DateTimeImmutable $instant): bool
    {
        return $this->start <= $instant && $instant < $this->end;
    }

    public function equals(self $other): bool
    {
        return $this->start == $other->start && $this->end == $other->end;
    }
}
