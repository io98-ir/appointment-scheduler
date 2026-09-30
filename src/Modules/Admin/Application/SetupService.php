<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Application;

use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Brand;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * The owner's look (white-label), how dates, digits and language are shown, and whether the setup wizard is done. Each
 * call checks the capability again after the REST permission callback
 * (architecture §12).
 */
final class SetupService
{
    /** Opens the plugin's admin app (short name, Caps::name()). */
    public const CAPABILITY = 'access_admin';

    public function __construct(private readonly Authorizer $authorizer, private readonly SetupStore $store)
    {
    }

    public function brand(): Brand
    {
        $this->authorize();

        return $this->store->brand();
    }

    /**
     * @throws InvalidValue What Brand rejects.
     */
    public function saveBrand(string $name, string $logoUrl, string $color): Brand
    {
        $this->authorize();
        $brand = Brand::of($name, $logoUrl, $color);
        $this->store->saveBrand($brand);

        return $brand;
    }

    public function display(): Display
    {
        $this->authorize();

        return $this->store->display();
    }

    public function saveDisplay(Display $display): Display
    {
        $this->authorize();
        $this->store->saveDisplay($display);

        return $display;
    }

    public function onboarded(): bool
    {
        $this->authorize();

        return $this->store->onboarded();
    }

    public function setOnboarded(bool $done): void
    {
        $this->authorize();
        $this->store->setOnboarded($done);
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }
}
