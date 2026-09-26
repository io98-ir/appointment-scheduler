<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\ResourceLocker;

/**
 * ResourceLocker on resource_day_locks (ADR-004): the rows are created if
 * missing, then locked with FOR UPDATE in (lock_key, day) order. A deadlock
 * or lock wait timeout is retried by the Transaction.
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
    public function lock(array $keys, int $from, int $to): void
    {
        if (!$this->db->inTransaction()) {
            throw new \LogicException('Locks are taken inside a transaction.');
        }
        if ([] === $keys || $from >= $to) {
            return;
        }
        $keys = \array_values(\array_unique($keys));
        \sort($keys, \SORT_STRING);
        $days = [];
        for ($day = \intdiv($from, 86_400); $day <= \intdiv($to - 1, 86_400); ++$day) {
            $days[] = \gmdate('Y-m-d', $day * 86_400);
        }

        $this->db->execute('SET SESSION innodb_lock_wait_timeout = %d', self::LOCK_WAIT_SECONDS);
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
        $placeholders = \implode(',', \array_fill(0, \count($keys), '%s'));
        $this->db->getResults(
            'SELECT lock_key FROM %i WHERE lock_key IN (' . $placeholders . ') AND day BETWEEN %s AND %s
            ORDER BY lock_key, day FOR UPDATE',
            Tables::name('resource_day_locks'),
            ...[...$keys, $days[0], $days[\count($days) - 1]]
        );
    }
}
