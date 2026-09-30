<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Settings;

/**
 * The switchable modules the owner turned off (T6.2). Read on every request
 * before the modules boot, so it is autoloaded.
 */
final class ModuleSettings implements SettingsGroup
{
    /**
     * @param list<string> $disabled Module ids.
     */
    public function __construct(public readonly array $disabled = [])
    {
    }

    public static function name(): string
    {
        return 'modules';
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
        $disabled = $stored['disabled'] ?? null;
        if (!\is_array($disabled)) {
            return new self();
        }

        return new self(\array_values(\array_unique(\array_filter(
            $disabled,
            static fn (mixed $id): bool => \is_string($id) && 1 === \preg_match('/^[a-z][a-z0-9_]*$/D', $id)
        ))));
    }

    /**
     * @return array{disabled: list<string>}
     */
    public function toStored(): array
    {
        return ['disabled' => $this->disabled];
    }
}
