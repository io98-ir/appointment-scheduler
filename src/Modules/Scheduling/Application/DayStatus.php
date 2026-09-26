<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Application;

/**
 * A day of the month view (booking-engine §2): it has a free start, it is
 * worked but taken, or nobody works it (a holiday, a day off, or outside
 * the booking window).
 */
enum DayStatus: string
{
    case Available = 'available';
    case Full = 'full';
    case Closed = 'closed';
}
