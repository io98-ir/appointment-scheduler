<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Infrastructure;

use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Scheduling\Application\BookingRulesStore;
use Vaqtyar\Modules\Scheduling\Domain\Availability\BookingRules;

/**
 * BookingRulesStore on the AvailabilitySettings group.
 */
final class SettingsBookingRulesStore implements BookingRulesStore
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function rules(): BookingRules
    {
        $stored = $this->settings->get(AvailabilitySettings::class);

        // What is stored has already passed the same bounds in AvailabilitySettings.
        return BookingRules::of(
            $stored->slotStepMin,
            $stored->minNoticeMin,
            $stored->maxAdvanceDays,
            $stored->staffChoice
        );
    }

    public function save(BookingRules $rules): void
    {
        $this->settings->save(new AvailabilitySettings(
            $rules->slotStepMin,
            $rules->minNoticeMin,
            $rules->maxAdvanceDays,
            $rules->staffChoice
        ));
        // The offered times are cached by the old rules.
        \do_action(Hooks::name('scheduling/changed'));
    }
}
