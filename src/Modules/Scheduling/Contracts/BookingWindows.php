<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Contracts;

/**
 * Where availability asks whether a service has a booking window of its own. Booking owns the
 * policies and implements this, so Scheduling does not depend on Booking (architecture §3).
 */
interface BookingWindows
{
    public function forService(int $serviceId): BookingWindow;
}
