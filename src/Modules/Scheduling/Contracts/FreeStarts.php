<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Contracts;

use Vaqtyar\Shared\Domain\LocalDate;

/**
 * Whether a day has a start a customer could book, as the availability views
 * count it (the booking window applies; what is booked is left out). For
 * Booking's waiting list, which asks after a cancellation.
 */
interface FreeStarts
{
    /**
     * @return list<int> UTC seconds of each start offered on the day at the query's location, earliest first.
     * @throws \Vaqtyar\Shared\Domain\NotFound when the variant or the location cannot be booked.
     */
    public function startsOn(AvailabilityQuery $query, LocalDate $date): array;
}
