<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Settings;

/**
 * One group of settings, stored as one option (Settings). A group is a
 * readonly class with typed properties, so a reader never sees a raw array.
 * Each module that has settings defines its own groups.
 */
interface SettingsGroup
{
    /**
     * Stable name, part of the option name: settings_{name}. a-z, 0-9 and _.
     */
    public static function name(): string;

    /**
     * Whether the option loads with every request. False for anything large
     * or read on few pages (principles §8).
     */
    public static function autoload(): bool;

    /**
     * Builds the group from what is stored. Never throws: a missing or
     * invalid value (an older version, a hand-edited option) falls back to
     * its default, so a bad option cannot break the site.
     *
     * @param array<mixed> $stored
     */
    public static function fromStored(array $stored): static;

    /**
     * @return array<string, int|string|bool|list<int|string>>
     */
    public function toStored(): array;
}
