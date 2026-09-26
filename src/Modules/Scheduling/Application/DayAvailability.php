<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Application;

use Vaqtyar\Modules\Scheduling\Domain\Availability\Slot;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * One local day of the location: its status and free starts.
 */
final class DayAvailability
{
    /**
     * @param list<Slot> $slots By start; empty unless Available.
     */
    public function __construct(
        public readonly LocalDate $date,
        public readonly DayStatus $status,
        public readonly array $slots,
    ) {
    }
}
