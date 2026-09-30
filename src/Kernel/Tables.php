<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * Builds table names (ADR-000, data-model §1): {$wpdb->prefix}{PREFIX}_{table}.
 *
 * Queries pass the result as a %i placeholder (Db), but DDL cannot be
 * prepared and takes it as-is, so only lowercase identifiers are accepted.
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

        $name = self::prefix() . $table;
        if (\strlen($name) > self::MAX_LENGTH) {
            throw KernelException::nameTooLong('table', $name, self::MAX_LENGTH);
        }

        return $name;
    }

    /**
     * A table of another plugin on this site, e.g. Action Scheduler's, which
     * we read or clean but never create: {$wpdb->prefix}{table}.
     */
    public static function external(string $table): string
    {
        if (1 !== \preg_match('/^[a-z][a-z0-9_]*$/D', $table)) {
            throw KernelException::invalidName('table', $table, 'use a-z, 0-9 and _, starting with a letter');
        }

        return \substr(self::prefix(), 0, -\strlen(Identity::PREFIX . '_')) . $table;
    }

    /**
     * What every table name of this site starts with: {$wpdb->prefix}{PREFIX}_.
     */
    public static function prefix(): string
    {
        // Read on every call: switch_to_blog() changes the prefix on multisite.
        $wpdb = $GLOBALS['wpdb'] ?? null;
        if (!\is_object($wpdb) || !isset($wpdb->prefix) || !\is_string($wpdb->prefix)) {
            throw KernelException::wpdbUnavailable();
        }

        return $wpdb->prefix . Identity::PREFIX . '_';
    }
}
