<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel\Database;

use Vaqtyar\Kernel\Database\Db;

/**
 * For tests that need real commits, so they extend TestCase and not
 * WP_UnitTestCase: that one wraps each test in a transaction it rolls back
 * and turns CREATE TABLE into CREATE TEMPORARY TABLE (implementation-notes §5).
 * Such tests clean up after themselves.
 */
trait RealDatabase
{
    private function wpdb(): \wpdb
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;
        if (!$wpdb instanceof \wpdb) {
            self::fail('$wpdb is not set up.');
        }

        return $wpdb;
    }

    private function realDb(): Db
    {
        // WP_UnitTestCase turns autocommit off and never turns it back on.
        $this->wpdb()->query('SET autocommit = 1');

        return new Db($this->wpdb());
    }

    /**
     * A second connection, as another request would have.
     */
    private function otherConnection(): \mysqli
    {
        $host = $this->wpdb()->parse_db_host(self::config('DB_HOST'));
        if (false === $host) {
            self::fail('DB_HOST cannot be parsed.');
        }
        [$hostname, $port, $socket] = $host;

        return new \mysqli(
            (string) $hostname,
            self::config('DB_USER'),
            self::config('DB_PASSWORD'),
            self::config('DB_NAME'),
            null === $port ? null : (int) $port,
            null === $socket ? null : (string) $socket
        );
    }

    private function dropTable(string $table): void
    {
        $this->realDb()->execute('DROP TABLE IF EXISTS %i', $table);
    }

    /**
     * A wp-config.php constant, which only exists once WordPress has loaded.
     */
    private static function config(string $name): string
    {
        $value = \constant($name);
        if (!\is_string($value)) {
            self::fail("{$name} is not set.");
        }

        return $value;
    }
}
