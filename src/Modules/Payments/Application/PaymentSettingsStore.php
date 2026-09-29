<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

/**
 * Where the gateway merchant ids and the WooCommerce switch are kept. A
 * merchant id is a secret (encrypted, or a wp-config.php constant).
 */
interface PaymentSettingsStore
{
    public function hasSecret(string $name): bool;

    /**
     * An empty value removes it.
     */
    public function setSecret(string $name, string $value): void;

    /**
     * A secret defined in wp-config.php cannot be changed here.
     */
    public function isFixed(string $name): bool;

    /**
     * Whether WooCommerce is active on this site.
     */
    public function wooAvailable(): bool;

    public function wooEnabled(): bool;

    public function setWooEnabled(bool $enabled): void;
}
