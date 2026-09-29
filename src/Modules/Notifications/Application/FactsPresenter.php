<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

use Vaqtyar\Modules\Booking\Contracts\AppointmentFacts;

/**
 * Turns facts into the words of a template's placeholders, in the site's
 * calendar and digits (DateFormatter).
 */
interface FactsPresenter
{
    /**
     * @return array<string, string> placeholder name to text: code, customer_name, service,
     *     staff, location, date, time, end_time, party_size and total.
     */
    public function values(AppointmentFacts $facts): array;
}
