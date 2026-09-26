<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

use DateTimeImmutable;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * A change of the base price for starts at some local times: e.g. +20% on
 * Friday evenings, or -10% before noon in a date range. A price_rules row
 * of type "time".
 */
final class TimeRule
{
    public const MIN_PERCENT = -100;
    public const MAX_PERCENT = 1000;

    /**
     * @param list<int> $weekdays 0 = Saturday … 6 = Friday (ScheduleRule's numbering); empty is every day.
     * @param int $fromMin local minute of day the start may be at, inclusive.
     * @param int $toMin local minute of day the start must be before, exclusive.
     * @param ?LocalDate $validFrom the first local date it applies, inclusive.
     * @param ?LocalDate $validTo the last local date it applies, inclusive.
     * @param int $percent of the base price, added; negative is a discount.
     */
    public function __construct(
        public readonly int $id,
        public readonly array $weekdays,
        public readonly int $fromMin,
        public readonly int $toMin,
        public readonly ?LocalDate $validFrom,
        public readonly ?LocalDate $validTo,
        public readonly int $percent,
    ) {
        foreach ($weekdays as $weekday) {
            if ($weekday < 0 || $weekday > 6) {
                throw new InvalidValue('invalid_weekday', 'A weekday is 0 (Saturday) to 6 (Friday).');
            }
        }
        if ($fromMin < 0 || $toMin > 1440 || $fromMin >= $toMin) {
            throw new InvalidValue('invalid_time_range', 'A time range is within a day and ends after it starts.');
        }
        if (null !== $validFrom && null !== $validTo && $validTo->isBefore($validFrom)) {
            throw new InvalidValue('invalid_date_range', 'A date range ends on or after it starts.');
        }
        if ($percent < self::MIN_PERCENT || $percent > self::MAX_PERCENT || 0 === $percent) {
            throw new InvalidValue('invalid_percent', 'A time rule changes the price by -100% to +1000%, not 0.');
        }
    }

    public function matches(PriceContext $context): bool
    {
        $local = (new DateTimeImmutable('@' . $context->start))->setTimezone($context->zone);
        $date = LocalDate::fromDateTime($local, $context->zone);
        $minute = (int) $local->format('G') * 60 + (int) $local->format('i');
        // ISO 1 = Monday … 6 = Saturday, 7 = Sunday, to 0 = Saturday.
        $weekday = ($date->dayOfWeek() + 1) % 7;

        return ([] === $this->weekdays || \in_array($weekday, $this->weekdays, true))
            && $minute >= $this->fromMin && $minute < $this->toMin
            && (null === $this->validFrom || !$date->isBefore($this->validFrom))
            && (null === $this->validTo || !$this->validTo->isBefore($date));
    }
}
