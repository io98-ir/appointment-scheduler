<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Database;

/**
 * A database operation failed at run time.
 *
 * The message never contains the server's error text: MySQL quotes the
 * offending value ("Duplicate entry '…'"), which may be user input, and PHP
 * prints an uncaught exception's message raw (implementation-notes §6). The
 * text is kept in $detail for the log.
 */
final class DbException extends \RuntimeException
{
    /** MySQL ER_LOCK_DEADLOCK. */
    public const DEADLOCK = 1213;

    /** MySQL ER_LOCK_WAIT_TIMEOUT. */
    public const LOCK_WAIT_TIMEOUT = 1205;

    /** MySQL CR_SERVER_GONE_ERROR. */
    public const SERVER_GONE_AWAY = 2006;

    private function __construct(
        string $message,
        public readonly string $detail,
        public readonly int $errno,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $errno, $previous);
    }

    public static function queryFailed(string $detail, int $errno): self
    {
        return new self(\sprintf('A database query failed (MySQL error %d).', $errno), $detail, $errno);
    }

    /**
     * Rolling back failed after $cause, so the connection is in an unknown
     * state. $cause is kept as the previous exception.
     */
    public static function rollbackFailed(self $rollback, \Throwable $cause): self
    {
        return new self(
            \sprintf('Rolling back a transaction failed (MySQL error %d).', $rollback->errno),
            $rollback->detail,
            $rollback->errno,
            $cause
        );
    }

    /**
     * wpdb reconnected in the middle of a transaction. Not retryable: the
     * statement it replayed on the new connection may have autocommitted, and
     * running the work again could write it twice.
     */
    public static function connectionLost(): self
    {
        $detail = 'The database connection was lost and reopened during a transaction.';

        return new self($detail, $detail, self::SERVER_GONE_AWAY);
    }

    public static function notInnoDb(string $table, string $engine): self
    {
        $detail = \sprintf(
            'The table %s uses the %s storage engine. This plugin needs InnoDB for transactions and row locks; '
            . 'ask your host to enable InnoDB, then deactivate and activate the plugin again.',
            $table,
            '' === $engine ? 'unknown' : $engine
        );

        return new self($detail, $detail, 0);
    }

    /**
     * Deadlocks and lock wait timeouts end the transaction's attempt, not the
     * request: running it again usually succeeds (ADR-004).
     */
    public function isRetryable(): bool
    {
        return self::DEADLOCK === $this->errno || self::LOCK_WAIT_TIMEOUT === $this->errno;
    }
}
