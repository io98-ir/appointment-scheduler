<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure;

use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Modules\Booking\Application\WaitlistNotifier;
use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistEntry;

/**
 * Announces a time that opened for someone waiting with the `{prefix}/waitlist/slot_opened` action
 * (customer id, the day as YYYY-MM-DD, the earliest start in UTC seconds, the service variant id, the
 * page of the site to send them to). Notifications answers it with an SMS; nothing else is needed here.
 */
final class HookWaitlistNotifier implements WaitlistNotifier
{
    public function slotOpened(WaitlistEntry $entry, int $firstStart): void
    {
        \do_action(
            Hooks::name('waitlist/slot_opened'),
            $entry->customerId,
            $entry->date->toString(),
            $firstStart,
            $entry->variantId,
            $entry->pageUrl
        );
    }
}
