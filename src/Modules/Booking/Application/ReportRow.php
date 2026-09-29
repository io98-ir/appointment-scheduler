<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;

/**
 * One group of a report query: how many appointments of one status, and
 * their price_total (IRR), for one key (a local date, or a service or staff
 * id, as text).
 */
final class ReportRow
{
    public function __construct(
        public readonly string $key,
        public readonly AppointmentStatus $status,
        public readonly int $count,
        public readonly int $revenue,
    ) {
    }
}
