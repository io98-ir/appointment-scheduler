<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Infrastructure;

use Vaqtyar\Kernel\Settings\SettingsGroup;

/**
 * Whether a guest must verify their phone with a one-time code before a
 * booking (T4.3). Off until a site can deliver codes (an SMS provider set up
 * in Notifications, or its own gateway on the customers/otp action).
 */
final class LoginSettings implements SettingsGroup
{
    public function __construct(public readonly bool $requirePhoneVerification = false)
    {
    }

    public static function name(): string
    {
        return 'customer_login';
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
        return new self(true === ($stored['require_phone_verification'] ?? null));
    }

    /**
     * @return array{require_phone_verification: bool}
     */
    public function toStored(): array
    {
        return ['require_phone_verification' => $this->requirePhoneVerification];
    }
}
