<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Application;

use Vaqtyar\Shared\Domain\Brand;

/**
 * Where the white-label look and the onboarding state are kept.
 */
interface SetupStore
{
    public function brand(): Brand;

    public function saveBrand(Brand $brand): void;

    /**
     * Whether the owner finished, or skipped, the setup wizard.
     */
    public function onboarded(): bool;

    public function setOnboarded(bool $done): void;

    public function display(): Display;

    public function saveDisplay(Display $display): void;
}
