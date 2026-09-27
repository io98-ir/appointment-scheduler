<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * What the admin list of appointments narrows to. Every condition holds at
 * once; an empty list or a null means any.
 */
final class AppointmentFilter
{
    /**
     * @param list<AppointmentStatus> $statuses
     * @param list<int> $staffIds
     * @param ?LocalDate $from the first local date (the location's), inclusive.
     * @param ?LocalDate $to the last local date, inclusive.
     */
    public function __construct(
        public readonly array $statuses = [],
        public readonly array $staffIds = [],
        public readonly ?int $serviceId = null,
        public readonly ?int $locationId = null,
        public readonly ?int $customerId = null,
        public readonly ?LocalDate $from = null,
        public readonly ?LocalDate $to = null,
    ) {
    }
}
