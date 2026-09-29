<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Query;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\ReportQuery;
use Vaqtyar\Modules\Booking\Application\ReportRow;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * ReportQuery on the appointments table. The exact filter is the local date;
 * a UTC range on start_at (indexed) keeps the scan to the days asked for,
 * with a day's slack each side for the local-to-UTC difference.
 */
final class WpdbReportQuery implements ReportQuery
{
    public function __construct(private readonly Db $db)
    {
    }

    /**
     * @return list<ReportRow>
     */
    public function byStatus(LocalDate $from, LocalDate $to, ?int $locationId): array
    {
        return self::rows($this->db->getResults(
            'SELECT status AS k, status, COUNT(*) AS n, COALESCE(SUM(price_total), 0) AS revenue FROM %i
            WHERE start_at >= %s AND start_at < %s AND local_date BETWEEN %s AND %s
            AND (%d = 0 OR location_id = %d)
            GROUP BY status',
            Tables::name('appointments'),
            ...self::args($from, $to, $locationId)
        ));
    }

    /**
     * @return list<ReportRow>
     */
    public function byDay(LocalDate $from, LocalDate $to, ?int $locationId): array
    {
        return self::rows($this->db->getResults(
            'SELECT local_date AS k, status, COUNT(*) AS n, COALESCE(SUM(price_total), 0) AS revenue FROM %i
            WHERE start_at >= %s AND start_at < %s AND local_date BETWEEN %s AND %s
            AND (%d = 0 OR location_id = %d)
            GROUP BY local_date, status',
            Tables::name('appointments'),
            ...self::args($from, $to, $locationId)
        ));
    }

    /**
     * @return list<ReportRow>
     */
    public function byService(LocalDate $from, LocalDate $to, ?int $locationId): array
    {
        return self::rows($this->db->getResults(
            'SELECT service_id AS k, status, COUNT(*) AS n, COALESCE(SUM(price_total), 0) AS revenue FROM %i
            WHERE start_at >= %s AND start_at < %s AND local_date BETWEEN %s AND %s
            AND (%d = 0 OR location_id = %d)
            GROUP BY service_id, status',
            Tables::name('appointments'),
            ...self::args($from, $to, $locationId)
        ));
    }

    /**
     * @return list<ReportRow>
     */
    public function byStaff(LocalDate $from, LocalDate $to, ?int $locationId): array
    {
        return self::rows($this->db->getResults(
            'SELECT staff_id AS k, status, COUNT(*) AS n, COALESCE(SUM(price_total), 0) AS revenue FROM %i
            WHERE start_at >= %s AND start_at < %s AND local_date BETWEEN %s AND %s
            AND (%d = 0 OR location_id = %d)
            GROUP BY staff_id, status',
            Tables::name('appointments'),
            ...self::args($from, $to, $locationId)
        ));
    }

    /**
     * @return list<int|string>
     */
    private static function args(LocalDate $from, LocalDate $to, ?int $locationId): array
    {
        $utc = new \DateTimeZone('UTC');

        return [
            $from->addDays(-1)->startOfDay($utc)->format('Y-m-d H:i:s'),
            $to->addDays(2)->startOfDay($utc)->format('Y-m-d H:i:s'),
            $from->toString(),
            $to->toString(),
            $locationId ?? 0,
            $locationId ?? 0,
        ];
    }

    /**
     * @param list<array<string, string|null>> $results
     * @return list<ReportRow>
     */
    private static function rows(array $results): array
    {
        $rows = [];
        foreach ($results as $values) {
            $row = new Row($values);
            $status = AppointmentStatus::tryFrom($row->string('status'));
            if (null !== $status) {
                $rows[] = new ReportRow($row->string('k'), $status, $row->int('n'), $row->int('revenue'));
            }
        }

        return $rows;
    }
}
