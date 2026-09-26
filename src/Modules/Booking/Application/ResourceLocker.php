<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

/**
 * Serializes the writers of the same calendars (ADR-004): prepare() before
 * the transaction, then lock() first in it, before any read the decision
 * depends on, so those reads see every booking committed before the locks
 * were granted.
 */
interface ResourceLocker
{
    /**
     * Makes sure what lock() will lock exists, outside any transaction, so
     * that competing transactions only wait on each other and never deadlock
     * creating it.
     *
     * @param list<string> $keys LockKey values, in any order.
     * @param int $from UTC seconds.
     * @param int $to UTC seconds.
     */
    public function prepare(array $keys, int $from, int $to): void;

    /**
     * Blocks until this transaction holds every key on every UTC day that
     * [from, to) touches. Two overlapping occupancies of one key always share
     * a day, so they cannot be written at the same time.
     *
     * @param list<string> $keys LockKey values, in any order.
     * @param int $from UTC seconds.
     * @param int $to UTC seconds.
     */
    public function lock(array $keys, int $from, int $to): void;
}
