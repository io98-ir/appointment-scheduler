<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * Runs a use case's work in one database transaction (Kernel\Database\
 * Transaction), for Application code that may not see the Kernel.
 */
interface TransactionRunner
{
    /**
     * Commits when $work returns and rolls back when it throws. $work may run
     * again from the start after a deadlock, so it reads everything it
     * decides on inside and has no side effects outside the database.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function run(callable $work): mixed;
}
