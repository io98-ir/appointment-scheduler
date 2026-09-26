<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain\Availability;

/**
 * Who comes first when the customer lets the business choose
 * (booking-engine §2): the least booked staff member that day, or the
 * admin's priority order. The first one is assigned at hold time.
 */
enum StaffChoice: string
{
    case LeastBusy = 'least_busy';
    case Priority = 'priority';
}
