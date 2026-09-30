<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain\Availability;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * The site-wide rules for what a customer may book: how far apart start
 * times are offered, how soon and how far ahead a booking can be, and who is
 * given the booking when the customer lets the business choose. A variant's
 * own step wins over the site's (AvailabilityDefaults).
 */
final class BookingRules
{
    public const MAX_STEP_MIN = 1440;
    public const MAX_NOTICE_MIN = 365 * 1440;
    public const MAX_ADVANCE_DAYS = 730;

    private function __construct(
        public readonly int $slotStepMin,
        public readonly int $minNoticeMin,
        public readonly int $maxAdvanceDays,
        public readonly StaffChoice $staffChoice,
    ) {
    }

    /**
     * @throws InvalidValue invalid_slot_step, invalid_min_notice or invalid_max_advance.
     */
    public static function of(int $slotStepMin, int $minNoticeMin, int $maxAdvanceDays, StaffChoice $staffChoice): self
    {
        if ($slotStepMin < 1 || $slotStepMin > self::MAX_STEP_MIN) {
            throw new InvalidValue('invalid_slot_step', 'The step between start times is 1 to 1440 minutes.');
        }
        if ($minNoticeMin < 0 || $minNoticeMin > self::MAX_NOTICE_MIN) {
            throw new InvalidValue('invalid_min_notice', 'The minimum notice is 0 minutes to a year.');
        }
        if ($maxAdvanceDays < 1 || $maxAdvanceDays > self::MAX_ADVANCE_DAYS) {
            throw new InvalidValue('invalid_max_advance', 'Booking can be open 1 to 730 days ahead.');
        }

        return new self($slotStepMin, $minNoticeMin, $maxAdvanceDays, $staffChoice);
    }
}
