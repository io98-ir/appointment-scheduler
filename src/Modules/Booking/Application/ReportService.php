<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * The dashboard and report read (T3.6): one call that folds the per-status
 * aggregates into what the admin screen shows. Checks the bookings
 * capability again, after the REST permission callback (architecture §12).
 */
final class ReportService
{
    public const MAX_DAYS = 366;

    public function __construct(
        private readonly ReportQuery $query,
        private readonly Authorizer $authorizer,
    ) {
    }

    /**
     * @throws InvalidValue invalid_range when the dates are out of order or
     *     span more than MAX_DAYS days.
     */
    public function summary(LocalDate $from, LocalDate $to, ?int $locationId): Report
    {
        if (!$this->authorizer->allows(BookingService::CAPABILITY)) {
            throw new Forbidden(BookingService::CAPABILITY);
        }
        if ($to->isBefore($from) || $from->daysUntil($to) >= self::MAX_DAYS) {
            throw new InvalidValue('invalid_range', 'A report covers 1 to 366 days, in order.');
        }

        $statuses = [];
        $booked = ['count' => 0, 'revenue' => 0];
        foreach ($this->query->byStatus($from, $to, $locationId) as $row) {
            $statuses[$row->status->value] = ($statuses[$row->status->value] ?? 0) + $row->count;
            if (self::isBooked($row->status)) {
                $booked['count'] += $row->count;
                $booked['revenue'] += $row->revenue;
            }
        }
        $cancelled = $statuses[AppointmentStatus::Cancelled->value] ?? 0;
        $noShow = $statuses[AppointmentStatus::NoShow->value] ?? 0;
        $decided = $booked['count'] + $cancelled + $noShow;

        return new Report(
            $from,
            $to,
            $booked['count'],
            $booked['revenue'],
            $cancelled,
            $noShow,
            0 === $decided ? 0.0 : \round($cancelled * 100 / $decided, 1),
            $statuses,
            self::days($from, $to, $this->query->byDay($from, $to, $locationId)),
            self::ranked($this->query->byService($from, $to, $locationId)),
            self::ranked($this->query->byStaff($from, $to, $locationId))
        );
    }

    private static function isBooked(AppointmentStatus $status): bool
    {
        return AppointmentStatus::Confirmed === $status || AppointmentStatus::Completed === $status;
    }

    /**
     * @param list<ReportRow> $rows
     * @return list<array{date: string, appointments: int, revenue: int}>
     */
    private static function days(LocalDate $from, LocalDate $to, array $rows): array
    {
        $byDate = [];
        foreach ($rows as $row) {
            if (self::isBooked($row->status)) {
                $day = $byDate[$row->key] ?? ['appointments' => 0, 'revenue' => 0];
                $byDate[$row->key] = [
                    'appointments' => $day['appointments'] + $row->count,
                    'revenue' => $day['revenue'] + $row->revenue,
                ];
            }
        }
        $days = [];
        for ($date = $from; !$to->isBefore($date); $date = $date->addDays(1)) {
            $key = $date->toString();
            $days[] = ['date' => $key] + ($byDate[$key] ?? ['appointments' => 0, 'revenue' => 0]);
        }

        return $days;
    }

    /**
     * @param list<ReportRow> $rows
     * @return list<array{id: int, appointments: int, revenue: int}>
     */
    private static function ranked(array $rows): array
    {
        $byId = [];
        foreach ($rows as $row) {
            if (!self::isBooked($row->status)) {
                continue;
            }
            $id = (int) $row->key;
            $current = $byId[$id] ?? ['id' => $id, 'appointments' => 0, 'revenue' => 0];
            $byId[$id] = [
                'id' => $id,
                'appointments' => $current['appointments'] + $row->count,
                'revenue' => $current['revenue'] + $row->revenue,
            ];
        }
        $ranked = \array_values($byId);
        \usort(
            $ranked,
            static fn (array $a, array $b): int => [$b['revenue'], $b['appointments'], $a['id']]
                <=> [$a['revenue'], $a['appointments'], $b['id']]
        );

        return $ranked;
    }
}
