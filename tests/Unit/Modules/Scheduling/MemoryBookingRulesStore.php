<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Scheduling;

use Vaqtyar\Modules\Scheduling\Application\BookingRulesStore;
use Vaqtyar\Modules\Scheduling\Domain\Availability\BookingRules;
use Vaqtyar\Modules\Scheduling\Domain\Availability\StaffChoice;

final class MemoryBookingRulesStore implements BookingRulesStore
{
    public ?BookingRules $saved = null;

    public function rules(): BookingRules
    {
        return $this->saved ?? BookingRules::of(30, 60, 60, StaffChoice::LeastBusy);
    }

    public function save(BookingRules $rules): void
    {
        $this->saved = $rules;
    }
}
