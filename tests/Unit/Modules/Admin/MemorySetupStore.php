<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Admin;

use Vaqtyar\Modules\Admin\Application\Display;
use Vaqtyar\Modules\Admin\Application\SetupStore;
use Vaqtyar\Shared\Domain\Calendar;
use Vaqtyar\Shared\Domain\Digits;
use Vaqtyar\Shared\Domain\Brand;
use Vaqtyar\Shared\Domain\Language;

final class MemorySetupStore implements SetupStore
{
    public Brand $brand;
    public bool $onboarded = false;
    public Display $display;

    public function __construct()
    {
        $this->brand = Brand::none();
        $this->display = new Display(Calendar::Jalali, Digits::Persian, Language::Auto);
    }

    public function display(): Display
    {
        return $this->display;
    }

    public function saveDisplay(Display $display): void
    {
        $this->display = $display;
    }

    public function brand(): Brand
    {
        return $this->brand;
    }

    public function saveBrand(Brand $brand): void
    {
        $this->brand = $brand;
    }

    public function onboarded(): bool
    {
        return $this->onboarded;
    }

    public function setOnboarded(bool $done): void
    {
        $this->onboarded = $done;
    }
}
