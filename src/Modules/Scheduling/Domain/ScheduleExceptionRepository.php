<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain;

use Vaqtyar\Shared\Domain\LocalDate;

/**
 * The stored exceptions. A deleted one is gone: exceptions are not catalog
 * items that appointments point at (data-model §1).
 */
interface ScheduleExceptionRepository
{
    public function find(int $id): ?ScheduleException;

    /**
     * One query for all the owners (availability reads every candidate).
     *
     * @param list<Owner> $owners
     * @return list<ScheduleException> Dates $from to $to, both included, by date and start.
     */
    public function between(array $owners, LocalDate $from, LocalDate $to): array;

    /**
     * Inserts when the id is null, else updates the stored row.
     *
     * @return ScheduleException As stored, with its id.
     */
    public function save(ScheduleException $exception): ScheduleException;

    public function delete(int $id): void;
}
