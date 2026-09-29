<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Infrastructure;

use Vaqtyar\Kernel\Settings\SettingsGroup;

/**
 * Whether the setup wizard is done. Read on every admin page load of the
 * plugin only, so not autoloaded.
 */
final class OnboardingSettings implements SettingsGroup
{
    public function __construct(public readonly bool $done = false)
    {
    }

    public static function name(): string
    {
        return 'onboarding';
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
        return new self(true === ($stored['done'] ?? null));
    }

    /**
     * @return array{done: bool}
     */
    public function toStored(): array
    {
        return ['done' => $this->done];
    }
}
