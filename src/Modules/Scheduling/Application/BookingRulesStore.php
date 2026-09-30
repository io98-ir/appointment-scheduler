<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Application;

use Vaqtyar\Modules\Scheduling\Domain\Availability\BookingRules;

/**
 * Where the site-wide booking rules are kept.
 */
interface BookingRulesStore
{
    public function rules(): BookingRules;

    /**
     * Saves them and makes the offered times, which were computed under the
     * old rules, be computed again.
     */
    public function save(BookingRules $rules): void;
}
