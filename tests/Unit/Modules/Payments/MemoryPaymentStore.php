<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use Vaqtyar\Modules\Payments\Application\PaymentSettingsStore;

final class MemoryPaymentStore implements PaymentSettingsStore
{
    public bool $woo = false;

    /**
     * @param array<string, string> $secrets
     * @param list<string> $fixed
     */
    public function __construct(public array $secrets, private readonly array $fixed)
    {
    }

    public function hasSecret(string $name): bool
    {
        return isset($this->secrets[$name]) || \in_array($name, $this->fixed, true);
    }

    public function setSecret(string $name, string $value): void
    {
        if ('' === $value) {
            unset($this->secrets[$name]);
        } else {
            $this->secrets[$name] = $value;
        }
    }

    public function isFixed(string $name): bool
    {
        return \in_array($name, $this->fixed, true);
    }

    public function wooAvailable(): bool
    {
        return true;
    }

    public function wooEnabled(): bool
    {
        return $this->woo;
    }

    public function setWooEnabled(bool $enabled): void
    {
        $this->woo = $enabled;
    }
}
