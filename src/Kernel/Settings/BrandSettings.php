<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Settings;

use Vaqtyar\Shared\Domain\Brand;

/**
 * The white-label look (T6.1) as a settings group. The admin menu, the admin
 * app and the booking widget read it, so it is autoloaded.
 */
final class BrandSettings implements SettingsGroup
{
    public readonly Brand $brand;

    public function __construct(?Brand $brand = null)
    {
        $this->brand = $brand ?? Brand::none();
    }

    public static function name(): string
    {
        return 'brand';
    }

    public static function autoload(): bool
    {
        return true;
    }

    /**
     * @param array<mixed> $stored
     */
    public static function fromStored(array $stored): static
    {
        return new self(Brand::fromStored($stored));
    }

    /**
     * @return array{name: string, logo_url: string, color: string}
     */
    public function toStored(): array
    {
        return [
            'name' => $this->brand->name,
            'logo_url' => $this->brand->logoUrl,
            'color' => $this->brand->color,
        ];
    }
}
