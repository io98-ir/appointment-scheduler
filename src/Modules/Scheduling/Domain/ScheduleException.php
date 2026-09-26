<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;

/**
 * A change to an owner's hours on one date: time off, extra hours or blocked
 * time, for the whole day (no start and end) or a range of it.
 */
final class ScheduleException
{
    /** Bytes, the TEXT column. */
    public const NOTE_MAX_BYTES = 65_535;

    public function __construct(
        public readonly ?int $id,
        public readonly Owner $owner,
        public readonly LocalDate $date,
        public readonly ?LocalTime $start,
        public readonly ?LocalTime $end,
        public readonly ExceptionKind $kind,
        public readonly string $note,
    ) {
        if (null !== $id && $id < 1) {
            throw new InvalidValue('invalid_id', 'An id is a positive integer.');
        }
        if ((null === $start) !== (null === $end) || (null !== $start && null !== $end && !$start->isBefore($end))) {
            throw new InvalidValue('invalid_time_range', 'A time range must end after it starts.');
        }
        if (ExceptionKind::Extra === $kind && null === $start) {
            throw new InvalidValue('extra_needs_hours', 'Extra hours need a start and an end.');
        }
        if (\strlen($note) > self::NOTE_MAX_BYTES || false === \mb_check_encoding($note, 'UTF-8')) {
            throw new InvalidValue('invalid_note', 'A note must be UTF-8 text of at most 65535 bytes.');
        }
    }

    public function isWholeDay(): bool
    {
        return null === $this->start;
    }
}
