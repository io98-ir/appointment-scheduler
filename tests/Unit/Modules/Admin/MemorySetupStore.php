<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Admin;

use Vaqtyar\Modules\Admin\Application\SetupStore;
use Vaqtyar\Shared\Domain\Brand;

final class MemorySetupStore implements SetupStore
{
    public Brand $brand;
    public bool $onboarded = false;

    public function __construct()
    {
        $this->brand = Brand::none();
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
