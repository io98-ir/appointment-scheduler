<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain;

use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\Slug;

/**
 * A day off in a holiday calendar, such as "ir". A location names its
 * calendar, and the calendar and date are the holiday's identity.
 */
final class Holiday
{
    public function __construct(
        public readonly Slug $calendar,
        public readonly LocalDate $date,
        public readonly Name $title,
        public readonly HolidaySource $source,
    ) {
    }
}
