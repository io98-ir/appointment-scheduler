<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Settings;

/**
 * What happens to the plugin's data when the plugin is deleted (architecture
 * §5): nothing, unless the owner opted in. Read only by the settings screen
 * and by uninstall, so not autoloaded.
 */
final class DataSettings implements SettingsGroup
{
    public function __construct(public readonly bool $deleteOnUninstall = false)
    {
    }

    public static function name(): string
    {
        return 'data';
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
        return new self(true === ($stored['delete_on_uninstall'] ?? null));
    }

    /**
     * @return array{delete_on_uninstall: bool}
     */
    public function toStored(): array
    {
        return ['delete_on_uninstall' => $this->deleteOnUninstall];
    }
}
