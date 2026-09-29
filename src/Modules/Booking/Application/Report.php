<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Shared\Domain\LocalDate;

/**
 * The dashboard and report figures for a range of local dates. "Booked" is
 * confirmed and completed appointments; their price_total is the revenue
 * (M5 adds what was actually paid). Days without any are present with zeros.
 */
final class Report
{
    /**
     * @param int $appointments booked ones.
     * @param int $revenue IRR, of the booked ones.
     * @param float $cancelRate percent (0 to 100, one decimal) of the appointments that were decided: booked,
     *     no-show and cancelled.
     * @param array<string, int> $statuses every status with at least one appointment.
     * @param list<array{date: string, appointments: int, revenue: int}> $days
     * @param list<array{id: int, appointments: int, revenue: int}> $services by revenue, highest first.
     * @param list<array{id: int, appointments: int, revenue: int}> $staff by revenue, highest first.
     */
    public function __construct(
        public readonly LocalDate $from,
        public readonly LocalDate $to,
        public readonly int $appointments,
        public readonly int $revenue,
        public readonly int $cancelled,
        public readonly int $noShow,
        public readonly float $cancelRate,
        public readonly array $statuses,
        public readonly array $days,
        public readonly array $services,
        public readonly array $staff,
    ) {
    }
}
