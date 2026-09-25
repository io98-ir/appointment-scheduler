<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Contracts;

/**
 * Where local dates and working hours are reckoned.
 */
final class LocationInfo
{
    /**
     * @param ?string $holidayCalendar the slug of its holiday calendar (Scheduling).
     */
    public function __construct(
        public readonly int $locationId,
        public readonly \DateTimeZone $timezone,
        public readonly ?string $holidayCalendar,
    ) {
    }
}
