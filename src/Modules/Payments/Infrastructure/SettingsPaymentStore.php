<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Infrastructure;

use Vaqtyar\Kernel\SecretStore;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Payments\Application\PaymentSettingsStore;

/**
 * PaymentSettingsStore on the Kernel's SecretStore and Settings.
 */
final class SettingsPaymentStore implements PaymentSettingsStore
{
    public function __construct(private readonly SecretStore $secrets, private readonly Settings $settings)
    {
    }

    public function hasSecret(string $name): bool
    {
        return null !== $this->secrets->get($name);
    }

    public function setSecret(string $name, string $value): void
    {
        $this->secrets->set($name, $value);
    }

    public function isFixed(string $name): bool
    {
        return $this->secrets->isDefinedInConfig($name);
    }

    public function wooAvailable(): bool
    {
        return \function_exists('wc_create_order');
    }

    public function wooEnabled(): bool
    {
        return $this->settings->get(WooCommerceSettings::class)->enabled;
    }

    public function setWooEnabled(bool $enabled): void
    {
        $this->settings->save(new WooCommerceSettings($enabled));
    }
}
