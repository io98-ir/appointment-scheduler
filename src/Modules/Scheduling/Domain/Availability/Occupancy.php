<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain\Availability;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * Time a staff member or resource is taken, by an appointment or an unexpired
 * hold (the occupancies table): UTC seconds [start, end), buffers included.
 *
 * Occupancies of one session (a group booking, capacity > 1) share the
 * variant, the staff member and the exact span; a later customer can join
 * that session and nothing else (AvailabilityCalculator).
 */
final class Occupancy
{
    /**
     * @param int $seats the party size booked.
     * @param ?int $variantId what was booked.
     * @param ?int $staffId who serves it; on a resource it tells one staff
     *     member's session from another's.
     */
    public function __construct(
        public readonly int $start,
        public readonly int $end,
        public readonly int $seats = 1,
        public readonly ?int $variantId = null,
        public readonly ?int $staffId = null,
    ) {
        if ($start >= $end) {
            throw new InvalidValue('invalid_interval', 'An occupancy must end after it starts.');
        }
        if ($seats < 1) {
            throw new InvalidValue('invalid_seats', 'An occupancy takes at least one seat.');
        }
    }

    /**
     * Whether this is part of the session that $staffId would run for
     * $variantId over [from, to).
     */
    public function isSession(int $variantId, int $staffId, int $from, int $to): bool
    {
        return $this->variantId === $variantId
            && (null === $this->staffId || $this->staffId === $staffId)
            && $this->start === $from
            && $this->end === $to;
    }
}
