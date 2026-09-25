<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Settings;

use Vaqtyar\Kernel\Options;

/**
 * Reads and saves settings groups, one option each: settings_{group}.
 *
 * Nothing is kept between calls: get_option() already caches, and a cached
 * copy here would go stale after switch_to_blog() on a multisite.
 */
final class Settings
{
    /**
     * @template T of SettingsGroup
     * @param class-string<T> $group
     * @return T
     */
    public function get(string $group): SettingsGroup
    {
        $stored = \get_option(self::key($group), []);

        return $group::fromStored(\is_array($stored) ? $stored : []);
    }

    public function save(SettingsGroup $settings): void
    {
        // update_option() returns false for an unchanged value too, so its
        // result says nothing about failure.
        \update_option(self::key($settings::class), $settings->toStored(), $settings::autoload());
    }

    /**
     * @param class-string<SettingsGroup> $group
     */
    private static function key(string $group): string
    {
        return Options::key('settings_' . $group::name());
    }
}
