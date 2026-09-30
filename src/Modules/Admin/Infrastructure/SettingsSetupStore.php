<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Infrastructure;

use Vaqtyar\Kernel\Settings\BrandSettings;
use Vaqtyar\Kernel\Settings\GeneralSettings;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Admin\Application\Display;
use Vaqtyar\Modules\Admin\Application\SetupStore;
use Vaqtyar\Shared\Domain\Brand;

/**
 * SetupStore on the Kernel's Settings.
 */
final class SettingsSetupStore implements SetupStore
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function brand(): Brand
    {
        return $this->settings->get(BrandSettings::class)->brand;
    }

    public function saveBrand(Brand $brand): void
    {
        $this->settings->save(new BrandSettings($brand));
    }

    public function onboarded(): bool
    {
        return $this->settings->get(OnboardingSettings::class)->done;
    }

    public function setOnboarded(bool $done): void
    {
        $this->settings->save(new OnboardingSettings($done));
    }

    public function display(): Display
    {
        $general = $this->settings->get(GeneralSettings::class);

        return new Display($general->calendar, $general->digits, $general->language);
    }

    public function saveDisplay(Display $display): void
    {
        $this->settings->save(new GeneralSettings($display->calendar, $display->digits, $display->language));
    }
}
