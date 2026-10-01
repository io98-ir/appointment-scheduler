<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistEntry;
use Vaqtyar\Modules\Customers\Contracts\CustomerSummary;

/**
 * A waiting-list request as the admin list shows it, with who asked.
 */
final class WaitlistItem
{
    public function __construct(public readonly WaitlistEntry $entry, public readonly ?CustomerSummary $customer)
    {
    }
}
