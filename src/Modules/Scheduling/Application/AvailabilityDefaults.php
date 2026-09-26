<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Application;

use Vaqtyar\Modules\Scheduling\Domain\Availability\StaffChoice;

/**
 * The site-wide availability settings. A variant's own slot step wins over
 * $stepMin; per-service notice and advance come with the booking_window
 * policy (T2.5).
 */
final class AvailabilityDefaults
{
    public function __construct(
        public readonly int $stepMin = 30,
        public readonly int $minNoticeMin = 60,
        public readonly int $maxAdvanceMin = 60 * 1440,
        public readonly StaffChoice $choice = StaffChoice::LeastBusy,
    ) {
    }
}
