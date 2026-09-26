<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\ResourceLocker;

/**
 * ResourceLocker on resource_day_locks (ADR-004): one row per key and UTC
 * day, locked with FOR UPDATE in (lock_key, day) order.
 *
 * The rows are created by prepare(), outside the transaction. Inside it,
 * lock() only waits on rows that exist, which never deadlocks. Creating them
 * there instead does: an INSERT IGNORE that meets an existing row takes a
 * shared lock on it, and competing transactions that each hold one cannot
 * upgrade to FOR UPDATE (CI concurrency job, 7 of 30 lost). A missing row is
 * still created inside, so the lock never degrades to a gap lock, which
 * would not exclude anyone; a deadlock there is retried by the Transaction.
 */
final class WpdbResourceLocker implements ResourceLocker
{
    /** ADR-004: a waiting booking gives up after this many seconds and is retried. */
    private const LOCK_WAIT_SECONDS = 5;

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * @param list<string> $keys
     */
    public function prepare(array $keys, int $from, int $to): void
    {
        if ($this->db->inTransaction()) {
            throw new \LogicException('Lock rows are created before the transaction.');
        }
        [$keys, $days] = self::rows($keys, $from, $to);
        if ([] !== $keys) {
            $this->insert($keys, $days);
        }
    }

    /**
     * @param list<string> $keys
     */
    public function lock(array $keys, int $from, int $to): void
    {
        if (!$this->db->inTransaction()) {
            throw new \LogicException('Locks are taken inside a transaction.');
        }
        [$keys, $days] = self::rows($keys, $from, $to);
        if ([] === $keys) {
            return;
        }
        $this->db->execute('SET SESSION innodb_lock_wait_timeout = %d', self::LOCK_WAIT_SECONDS);
        if ($this->forUpdate($keys, $days) < \count($keys) * \count($days)) {
            $this->insert($keys, $days);
            $this->forUpdate($keys, $days);
        }
    }

    /**
     * @param list<string> $keys
     * @return array{list<string>, non-empty-list<string>} The keys unique and sorted, and the UTC days.
     */
    private static function rows(array $keys, int $from, int $to): array
    {
        $keys = $from < $to ? \array_values(\array_unique($keys)) : [];
        \sort($keys, \SORT_STRING);
        $first = \intdiv($from, 86_400);
        $days = [\gmdate('Y-m-d', $first * 86_400)];
        for ($day = $first + 1; $day <= \intdiv($to - 1, 86_400); ++$day) {
            $days[] = \gmdate('Y-m-d', $day * 86_400);
        }

        return [$keys, $days];
    }

    /**
     * @param non-empty-list<string> $keys
     * @param non-empty-list<string> $days
     */
    private function insert(array $keys, array $days): void
    {
        $values = [];
        foreach ($keys as $key) {
            foreach ($days as $day) {
                $values[] = $key;
                $values[] = $day;
            }
        }
        $rows = \implode(',', \array_fill(0, \count($keys) * \count($days), '(%s, %s)'));
        $this->db->execute(
            'INSERT IGNORE INTO %i (lock_key, day) VALUES ' . $rows,
            Tables::name('resource_day_locks'),
            ...$values
        );
    }

    /**
     * @param non-empty-list<string> $keys
     * @param non-empty-list<string> $days
     * @return int How many rows were locked.
     */
    private function forUpdate(array $keys, array $days): int
    {
        $placeholders = \implode(',', \array_fill(0, \count($keys), '%s'));

        return \count($this->db->getResults(
            'SELECT lock_key FROM %i WHERE lock_key IN (' . $placeholders . ') AND day BETWEEN %s AND %s
            ORDER BY lock_key, day FOR UPDATE',
            Tables::name('resource_day_locks'),
            ...[...$keys, $days[0], $days[\count($days) - 1]]
        ));
    }
}
