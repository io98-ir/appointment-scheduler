<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Domain;

/**
 * What makes a template send. `Reminder` is time-based: its template says
 * how many minutes before the start it goes out.
 */
enum Trigger: string
{
    case Booked = 'booked';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';
    case Reminder = 'reminder';
}
