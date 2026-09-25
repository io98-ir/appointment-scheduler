<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Database;

use Mockery;
use Mockery\MockInterface;

/**
 * A $wpdb double that records every statement it is given. WordPress is not
 * loaded in the unit suite, so Mockery declares the wpdb class itself.
 *
 * A test decides what a statement returns with respondWith(); a response of
 * false plus an error text is a failed query, as with the real wpdb.
 */
trait FakesWpdb
{
    /** @var list<string> */
    private array $queries = [];

    /** @var (\Closure(string): (int|bool|string|null))|null */
    private ?\Closure $responder = null;

    private \wpdb&MockInterface $wpdb;

    private function fakeWpdb(): \wpdb&MockInterface
    {
        /** @var \wpdb&MockInterface $wpdb */
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->insert_id = 0;
        $wpdb->shouldReceive('hide_errors')->andReturn(true)->byDefault();
        $wpdb->shouldReceive('show_errors')->andReturn(false)->byDefault();
        $wpdb->shouldReceive('get_charset_collate')->andReturn('DEFAULT CHARACTER SET utf8mb4')->byDefault();
        // No real connection, so no MySQL error number.
        $wpdb->shouldReceive('__get')->with('dbh')->andReturn(null)->byDefault();
        $wpdb->shouldReceive('prepare')->andReturnUsing(
            static fn (string $sql, int|string ...$args): string => \vsprintf(
                \str_replace(['%s', '%i'], ["'%s'", '`%s`'], $sql),
                $args
            )
        )->byDefault();
        $wpdb->shouldReceive('query')->andReturnUsing(function (string $sql): int|bool {
            $result = $this->respond($sql);

            return \is_int($result) || \is_bool($result) ? $result : true;
        })->byDefault();
        $wpdb->shouldReceive('get_var')->andReturnUsing(function (string $sql): ?string {
            $result = $this->respond($sql);

            return \is_string($result) ? $result : null;
        })->byDefault();

        $this->wpdb = $wpdb;
        $GLOBALS['wpdb'] = $wpdb;

        return $wpdb;
    }

    /**
     * @param \Closure(string): (int|bool|string|null) $responder
     */
    private function respondWith(\Closure $responder): void
    {
        $this->responder = $responder;
    }

    /**
     * A failed statement: wpdb returns false and keeps the error text.
     */
    private function failWith(string $error): bool
    {
        $this->wpdb->last_error = $error;

        return false;
    }

    private function respond(string $sql): int|bool|string|null
    {
        $this->queries[] = $sql;
        // wpdb clears the error at the start of every query.
        $this->wpdb->last_error = '';

        return null === $this->responder ? true : ($this->responder)($sql);
    }

    private function tearDownWpdb(): void
    {
        unset($GLOBALS['wpdb']);
        Mockery::close();
    }
}
