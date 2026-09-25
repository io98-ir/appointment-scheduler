<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Database;

/**
 * Runs work inside one InnoDB transaction, retrying on deadlock and lock wait
 * timeout (ADR-004).
 */
final class Transaction
{
    /** ADR-004: up to 3 retries after the first attempt. */
    private const MAX_RETRIES = 3;

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * Commits when $work returns and rolls back when it throws. On a deadlock
     * or lock wait timeout $work runs again from the start, so it must read
     * everything it decides on inside the transaction and have no side effects
     * outside the database.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function run(callable $work): mixed
    {
        for ($retry = 0;; $retry++) {
            $this->db->beginTransaction();
            try {
                $result = $work();
                $this->db->commit();

                return $result;
            } catch (\Throwable $e) {
                $this->rollBack($e);
                if (!$e instanceof DbException || !$e->isRetryable() || $retry >= self::MAX_RETRIES) {
                    throw $e;
                }
            }
            // Spread the retries of competing requests so they do not collide again.
            \usleep(\random_int(5_000, 20_000) * ($retry + 1));
        }
    }

    private function rollBack(\Throwable $cause): void
    {
        try {
            $this->db->rollBack();
        } catch (DbException $e) {
            throw DbException::rollbackFailed($e, $cause);
        }
    }
}
