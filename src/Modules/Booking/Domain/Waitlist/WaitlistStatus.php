<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Waitlist;

/**
 * Where a request to be told about a time stands: waiting, told once a time
 * opened, or over because the day has passed.
 */
enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Notified = 'notified';
    case Expired = 'expired';
}
