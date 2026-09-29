<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Shared\Domain\LocalDate;

/**
 * Aggregates over appointments by their local date, for the dashboard and
 * the reports (T3.6). Each returns one row per key and status; ReportService
 * decides which statuses count towards what.
 */
interface ReportQuery
{
    /**
     * @return list<ReportRow> keyed by the status value.
     */
    public function byStatus(LocalDate $from, LocalDate $to, ?int $locationId): array;

    /**
     * @return list<ReportRow> keyed by the local date, Y-m-d.
     */
    public function byDay(LocalDate $from, LocalDate $to, ?int $locationId): array;

    /**
     * @return list<ReportRow> keyed by the service id.
     */
    public function byService(LocalDate $from, LocalDate $to, ?int $locationId): array;

    /**
     * @return list<ReportRow> keyed by the staff id.
     */
    public function byStaff(LocalDate $from, LocalDate $to, ?int $locationId): array;
}
