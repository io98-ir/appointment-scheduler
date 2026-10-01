<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Application\WaitlistNotifier;
use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistEntry;

/**
 * Remembers each customer told, as "customer/day/first start".
 */
final class RecordingWaitlistNotifier implements WaitlistNotifier
{
    /** @var list<string> */
    public array $told = [];

    public function slotOpened(WaitlistEntry $entry, int $firstStart): void
    {
        $this->told[] = $entry->customerId . '/' . $entry->date->toString() . '/' . $firstStart;
    }
}
