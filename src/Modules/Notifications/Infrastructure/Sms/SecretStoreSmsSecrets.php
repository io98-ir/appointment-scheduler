<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Sms;

use Vaqtyar\Kernel\SecretStore;
use Vaqtyar\Modules\Notifications\Application\SmsSecrets;

/**
 * SmsSecrets on the Kernel's SecretStore.
 */
final class SecretStoreSmsSecrets implements SmsSecrets
{
    public function __construct(private readonly SecretStore $store)
    {
    }

    public function get(string $name): ?string
    {
        return $this->store->get($name);
    }

    public function set(string $name, string $value): void
    {
        $this->store->set($name, $value);
    }

    public function isFixed(string $name): bool
    {
        return $this->store->isDefinedInConfig($name);
    }
}
