<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain\Availability;

/**
 * A staff member who can take a slot.
 */
final class SlotStaff
{
    /**
     * @param int $end UTC seconds; their own duration plus extras, without the buffer.
     * @param int $seatsLeft seats free in the session before this booking (at
     *     least the party size). Only the staff side limits seats: a resource
     *     counts sessions, not seats.
     */
    public function __construct(
        public readonly int $staffId,
        public readonly int $end,
        public readonly int $seatsLeft,
    ) {
    }
}
