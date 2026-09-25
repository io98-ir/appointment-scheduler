<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Database;

use Vaqtyar\Kernel\KernelException;

/**
 * The only way our code talks to $wpdb: values always go through prepare(),
 * and every failure becomes a DbException instead of a silent false.
 *
 * Table names come from Tables::name() and go in as %i. One instance per
 * connection, so the transaction flag is shared (container singleton).
 */
final class Db
{
    private bool $inTransaction = false;

    /** The connection the open transaction started on. */
    private int $connectionId = 0;

    public function __construct(private readonly \wpdb $wpdb)
    {
    }

    public static function fromGlobals(): self
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;
        if (!$wpdb instanceof \wpdb) {
            throw KernelException::wpdbUnavailable();
        }

        return new self($wpdb);
    }

    /**
     * Runs a statement and returns the number of affected rows (0 for
     * statements that affect none).
     *
     * @param literal-string $sql Values go in as %s or %d and table names as %i,
     *     so nothing from outside the code is ever concatenated into SQL.
     */
    public function execute(string $sql, int|string ...$args): int
    {
        $sql = $this->prepare($sql, $args);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared just above when it has values.
        $result = $this->call(fn (): int|bool => $this->wpdb->query($sql));

        return \is_int($result) ? $result : 0;
    }

    /**
     * The first column of the first row, or null when there is no row or the
     * value is NULL.
     *
     * @param literal-string $sql As for execute().
     */
    public function getVar(string $sql, int|string ...$args): ?string
    {
        $sql = $this->prepare($sql, $args);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared just above when it has values.
        $value = $this->call(fn (): ?string => $this->wpdb->get_var($sql));

        return null === $value ? null : (string) $value;
    }

    /**
     * @param array<string, int|string|null> $data Column => value; null is stored as NULL.
     * @return int The AUTO_INCREMENT id of the new row.
     */
    public function insert(string $table, array $data): int
    {
        $this->assertIdentifiers($table, $data);
        $this->call(fn (): int|false => $this->wpdb->insert($table, $data, $this->formats($data)));

        return $this->wpdb->insert_id;
    }

    /**
     * @param array<string, int|string|null> $data Column => new value.
     * @param array<string, int|string|null> $where Column => value, joined with AND; null matches IS NULL.
     * @return int The number of rows changed (MySQL does not count rows whose values stay the same).
     */
    public function update(string $table, array $data, array $where): int
    {
        if ([] === $where) {
            throw KernelException::updateWithoutWhere($table);
        }
        $this->assertIdentifiers($table, $data + $where);
        $result = $this->call(fn (): int|false => $this->wpdb->update(
            $table,
            $data,
            $where,
            $this->formats($data),
            $this->formats($where)
        ));

        return (int) $result;
    }

    /**
     * Creates the table if it is missing, in the site's charset, and checks
     * that it is InnoDB, also when it existed already: without
     * NO_ENGINE_SUBSTITUTION MySQL silently falls back to another engine,
     * which has no transactions or row locks.
     *
     * @param string $table Full name, from Tables::name().
     * @param literal-string $definition The column and index list inside the parentheses.
     */
    public function createTable(string $table, string $definition): void
    {
        $this->assertIdentifiers($table, []);
        // DDL cannot be prepared: a validated name, columns from the code and the charset from WordPress.
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` ({$definition}) ENGINE=InnoDB "
            . $this->wpdb->get_charset_collate();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- see above.
        $this->call(fn (): int|bool => $this->wpdb->query($sql));

        $engine = $this->getVar(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            $table
        );
        if (null === $engine || 0 !== \strcasecmp($engine, 'InnoDB')) {
            throw DbException::notInnoDb($table, (string) $engine);
        }
    }

    /**
     * MySQL commits the open transaction silently on a second START
     * TRANSACTION, so nesting is refused rather than half-supported.
     */
    public function beginTransaction(): void
    {
        if ($this->inTransaction) {
            throw KernelException::nestedTransaction();
        }
        $this->execute('START TRANSACTION');
        $this->connectionId = $this->connectionId();
        $this->inTransaction = true;
    }

    public function commit(): void
    {
        $this->execute('COMMIT');
        $this->inTransaction = false;
    }

    public function rollBack(): void
    {
        // Cleared first: whatever ROLLBACK returns, no transaction is ours any more.
        $this->inTransaction = false;
        $this->execute('ROLLBACK');
    }

    /**
     * @param literal-string $sql
     * @param array<int|string> $args
     */
    private function prepare(string $sql, array $args): string
    {
        if ([] === $args) {
            return $sql;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- this is the prepare() call; callers pass placeholders.
        return (string) $this->wpdb->prepare($sql, ...\array_values($args));
    }

    /**
     * Runs one $wpdb call with its own error output off (we throw instead)
     * and turns a failure into a DbException.
     *
     * @template T
     * @param \Closure(): T $operation
     * @return T
     */
    private function call(\Closure $operation): mixed
    {
        $showErrors = $this->wpdb->hide_errors();
        try {
            $result = $operation();
        } finally {
            $this->wpdb->show_errors($showErrors);
        }

        // On "server has gone away" wpdb reconnects and replays the statement
        // on a new connection, which has no transaction and autocommits. The
        // locks and checks of this transaction are gone, so it must not go on.
        if ($this->inTransaction && $this->connectionId() !== $this->connectionId) {
            throw DbException::connectionLost();
        }

        // insert() and update() return false without an error text when a value
        // does not fit the column's charset or length.
        if (false === $result || '' !== $this->wpdb->last_error) {
            throw DbException::queryFailed(
                '' !== $this->wpdb->last_error ? $this->wpdb->last_error : 'The value does not fit the column.',
                $this->errno()
            );
        }

        return $result;
    }

    /**
     * The server's id for the current connection; 0 without a mysqli link.
     */
    private function connectionId(): int
    {
        $dbh = $this->wpdb->__get('dbh');

        return $dbh instanceof \mysqli ? (int) $dbh->thread_id : 0;
    }

    private function errno(): int
    {
        // Protected, but readable through wpdb's back-compat __get().
        $dbh = $this->wpdb->__get('dbh');

        return $dbh instanceof \mysqli ? \mysqli_errno($dbh) : 0;
    }

    /**
     * Column names cannot be placeholders either.
     *
     * @param array<string, int|string|null> $columns
     */
    private function assertIdentifiers(string $table, array $columns): void
    {
        foreach ([$table, ...\array_keys($columns)] as $name) {
            if (1 !== \preg_match('/^[A-Za-z0-9_]+$/D', (string) $name)) {
                throw KernelException::invalidName('SQL identifier', (string) $name, 'use A-Z, a-z, 0-9 and _');
            }
        }
    }

    /**
     * @param array<string, int|string|null> $values
     * @return list<string>
     */
    private function formats(array $values): array
    {
        return \array_values(\array_map(
            static fn (int|string|null $value): string => \is_int($value) ? '%d' : '%s',
            $values
        ));
    }
}
