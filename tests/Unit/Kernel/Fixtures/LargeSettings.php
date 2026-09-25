<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Fixtures;

use Vaqtyar\Kernel\Settings\SettingsGroup;

/**
 * A group too large to load on every request.
 */
final class LargeSettings implements SettingsGroup
{
    /**
     * @param list<string> $items
     */
    public function __construct(public readonly array $items = [])
    {
    }

    public static function name(): string
    {
        return 'large';
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
        $items = $stored['items'] ?? [];

        return new self(\is_array($items) ? \array_values(\array_filter($items, 'is_string')) : []);
    }

    /**
     * @return array{items: list<string>}
     */
    public function toStored(): array
    {
        return ['items' => $this->items];
    }
}
