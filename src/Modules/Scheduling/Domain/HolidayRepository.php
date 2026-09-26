<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain;

use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\Slug;

/**
 * The stored holidays, one per calendar and date.
 */
interface HolidayRepository
{
    /**
     * @return list<Holiday> Dates $from to $to, both included, by date.
     */
    public function between(Slug $calendar, LocalDate $from, LocalDate $to): array;

    /**
     * Adds the holiday, or replaces the one on that calendar and date.
     */
    public function save(Holiday $holiday): void;

    public function delete(Slug $calendar, LocalDate $date): void;

    /**
     * Adds a dataset's holidays but keeps any day already stored, so a day
     * the admin set or corrected wins over the dataset.
     *
     * @param list<Holiday> $holidays
     * @return int How many were added.
     */
    public function import(array $holidays): int;
}
