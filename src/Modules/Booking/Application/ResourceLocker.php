<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

/**
 * Serializes the writers of the same calendars (ADR-004). Call it first in
 * the transaction, before any read the decision depends on, so those reads
 * see every booking committed before the locks were granted.
 */
interface ResourceLocker
{
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
