<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain\Availability;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * What is being booked: the variant's rules (Offer), the customer's choices
 * (extras, party size) and the booking window, all in minutes.
 */
final class SlotRequest
{
    /**
     * @param int $capacity customers at the same time with one staff member (the service's).
     * @param int $extrasMin the chosen extras' total duration.
     * @param int $stepMin the start-time grid, counted from local midnight.
     * @param int $minNoticeMin the earliest start is now plus this.
     * @param int $maxAdvanceMin the latest start is now plus this.
     */
    public function __construct(
        public readonly int $variantId,
        public readonly int $capacity,
        public readonly int $partySize,
        public readonly int $extrasMin,
        public readonly int $bufferBeforeMin,
        public readonly int $bufferAfterMin,
        public readonly int $stepMin,
        public readonly int $minNoticeMin,
        public readonly int $maxAdvanceMin,
        public readonly StaffChoice $choice,
    ) {
        if ($partySize < 1) {
            throw new InvalidValue('invalid_party_size', 'A party is at least one person.');
        }
        if ($capacity < 1) {
            throw new InvalidValue('invalid_capacity', 'A capacity is at least one.');
        }
        if ($stepMin < 1 || $stepMin > 1440) {
            throw new InvalidValue('invalid_step', 'A slot step is 1 to 1440 minutes.');
        }
        if (\min($extrasMin, $bufferBeforeMin, $bufferAfterMin, $minNoticeMin, $maxAdvanceMin) < 0) {
            throw new InvalidValue('invalid_minutes', 'Durations and notice periods are not negative.');
        }
    }
}
