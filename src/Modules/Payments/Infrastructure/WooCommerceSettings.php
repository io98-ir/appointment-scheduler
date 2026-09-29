<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Infrastructure;

use Vaqtyar\Kernel\Settings\SettingsGroup;

/**
 * Whether bookings are paid through WooCommerce (T5.3). Off until the owner
 * turns it on, so installing WooCommerce for a shop does not change how
 * bookings are paid. The screen for it comes with onboarding (T6.1).
 */
final class WooCommerceSettings implements SettingsGroup
{
    public function __construct(public readonly bool $enabled = false)
    {
    }

    public static function name(): string
    {
        return 'woocommerce_gateway';
    }

    public static function autoload(): bool
    {
        return false;
    }

    /**
     * @param array<mixed> $stored
     */
    public static function fromStored(array $stored): static
    {
        return new self(true === ($stored['enabled'] ?? null));
    }

    /**
     * @return array{enabled: bool}
     */
    public function toStored(): array
    {
        return ['enabled' => $this->enabled];
    }
}
