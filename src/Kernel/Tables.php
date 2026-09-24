<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * Builds table names (ADR-000, data-model §1): {$wpdb->prefix}{PREFIX}_{table}.
 *
 * A table name cannot be a prepared-statement placeholder, so it goes into SQL
 * as-is; only lowercase identifiers are accepted.
 */
final class Tables
{
    /** MySQL identifier limit. */
    private const MAX_LENGTH = 64;

    public static function name(string $table): string
    {
        if (1 !== \preg_match('/^[a-z][a-z0-9_]*$/D', $table)) {
            throw KernelException::invalidName('table', $table, 'use a-z, 0-9 and _, starting with a letter');
        }

        // Read on every call: switch_to_blog() changes the prefix on multisite.
        $wpdb = $GLOBALS['wpdb'] ?? null;
        if (!\is_object($wpdb) || !isset($wpdb->prefix) || !\is_string($wpdb->prefix)) {
            throw KernelException::wpdbUnavailable();
        }

        $name = $wpdb->prefix . Identity::PREFIX . '_' . $table;
        if (\strlen($name) > self::MAX_LENGTH) {
            throw KernelException::nameTooLong('table', $name, self::MAX_LENGTH);
        }

        return $name;
    }
}
