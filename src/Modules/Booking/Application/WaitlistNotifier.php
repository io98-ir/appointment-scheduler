<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistEntry;

/**
 * Tells a customer on the waiting list that a time opened. Booking does not know how: the infrastructure
 * announces it and whichever module sends messages answers.
 */
interface WaitlistNotifier
{
    /**
     * @param int $firstStart UTC seconds of the earliest time offered on the day.
     */
    public function slotOpened(WaitlistEntry $entry, int $firstStart): void;
}
