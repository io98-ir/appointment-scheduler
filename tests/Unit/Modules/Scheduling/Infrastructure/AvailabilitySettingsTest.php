<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Scheduling\Infrastructure;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Scheduling\Application\AvailabilityDefaults;
use Vaqtyar\Modules\Scheduling\Domain\Availability\StaffChoice;
use Vaqtyar\Modules\Scheduling\Infrastructure\AvailabilitySettings;

final class AvailabilitySettingsTest extends TestCase
{
    public function testWhatIsStoredIsReadBack(): void
    {
        $settings = new AvailabilitySettings(15, 0, 90, StaffChoice::Priority);

        self::assertEquals($settings, AvailabilitySettings::fromStored($settings->toStored()));
        self::assertEquals(new AvailabilityDefaults(15, 0, 90 * 1440, StaffChoice::Priority), $settings->defaults());
    }

    public function testAMissingOrInvalidValueFallsBackToItsDefault(): void
    {
        $settings = AvailabilitySettings::fromStored([
            'slot_step_min' => 0,
            'min_notice_min' => '60',
            'max_advance_days' => 731,
            'staff_choice' => 'random',
        ]);

        self::assertEquals(new AvailabilitySettings(), $settings);
        self::assertEquals(new AvailabilitySettings(), AvailabilitySettings::fromStored([]));
    }
}
