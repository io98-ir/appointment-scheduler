<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Application\BookingService;
use Vaqtyar\Modules\Booking\Application\ReportQuery;
use Vaqtyar\Modules\Booking\Application\ReportRow;
use Vaqtyar\Modules\Booking\Application\ReportService;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;

final class ReportServiceTest extends TestCase
{
    public function testAnUnauthorizedCallerIsForbidden(): void
    {
        $this->expectException(Forbidden::class);

        self::service([], false)->summary(
            LocalDate::fromString('2026-10-01'),
            LocalDate::fromString('2026-10-01'),
            null
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badRanges(): iterable
    {
        yield 'reversed' => ['2026-10-03', '2026-10-01'];
        yield '367 days' => ['2025-10-01', '2026-10-02'];
    }

    /**
     * @dataProvider badRanges
     */
    public function testABadRangeIsRejected(string $from, string $to): void
    {
        $this->expectException(InvalidValue::class);

        self::service([], true)->summary(LocalDate::fromString($from), LocalDate::fromString($to), null);
    }

    public function testAYearOf366DaysIsAllowed(): void
    {
        $report = self::service([], true)->summary(
            LocalDate::fromString('2025-10-01'),
            LocalDate::fromString('2026-09-30'),
            null
        );

        self::assertCount(365, $report->days);
    }

    public function testOnlyConfirmedAndCompletedAreBookedAndTheCancelRateUsesTheDecidedOnes(): void
    {
        $rows = [
            new ReportRow('confirmed', AppointmentStatus::Confirmed, 6, 6_000_000),
            new ReportRow('completed', AppointmentStatus::Completed, 2, 3_000_000),
            new ReportRow('cancelled', AppointmentStatus::Cancelled, 2, 2_000_000),
            new ReportRow('no_show', AppointmentStatus::NoShow, 1, 1_000_000),
            new ReportRow('pending_payment', AppointmentStatus::PendingPayment, 4, 4_000_000),
        ];

        $report = self::service($rows, true)->summary(
            LocalDate::fromString('2026-10-01'),
            LocalDate::fromString('2026-10-01'),
            null
        );

        self::assertSame(
            [8, 9_000_000, 2, 1],
            [$report->appointments, $report->revenue, $report->cancelled, $report->noShow]
        );
        // 2 cancelled of 11 decided (8 booked, 1 no-show, 2 cancelled).
        self::assertSame(18.2, $report->cancelRate);
        self::assertSame(4, $report->statuses['pending_payment']);
    }

    public function testNothingDecidedIsAZeroRateNotADivisionByZero(): void
    {
        $report = self::service([], true)->summary(
            LocalDate::fromString('2026-10-01'),
            LocalDate::fromString('2026-10-01'),
            null
        );

        self::assertSame([0, 0.0], [$report->appointments, $report->cancelRate]);
    }

    public function testDaysAreFilledAndServicesAreRankedByRevenue(): void
    {
        $days = [
            new ReportRow('2026-10-02', AppointmentStatus::Confirmed, 1, 500),
            new ReportRow('2026-10-02', AppointmentStatus::Completed, 2, 700),
            new ReportRow('2026-10-02', AppointmentStatus::Cancelled, 3, 9_000),
        ];
        $services = [
            new ReportRow('7', AppointmentStatus::Confirmed, 1, 100),
            new ReportRow('9', AppointmentStatus::Confirmed, 1, 300),
            new ReportRow('9', AppointmentStatus::Completed, 2, 300),
            new ReportRow('8', AppointmentStatus::Confirmed, 2, 600),
            new ReportRow('6', AppointmentStatus::Cancelled, 5, 9_000),
        ];

        $report = self::service([], true, $days, $services)->summary(
            LocalDate::fromString('2026-10-01'),
            LocalDate::fromString('2026-10-03'),
            null
        );

        self::assertSame(
            [
                ['date' => '2026-10-01', 'appointments' => 0, 'revenue' => 0],
                ['date' => '2026-10-02', 'appointments' => 3, 'revenue' => 1_200],
                ['date' => '2026-10-03', 'appointments' => 0, 'revenue' => 0],
            ],
            $report->days
        );
        // 9 and 8 tie on revenue (600); 9 has more appointments.
        self::assertSame([9, 8, 7], \array_column($report->services, 'id'));
    }

    /**
     * @param list<ReportRow> $statuses
     * @param list<ReportRow> $days
     * @param list<ReportRow> $ids Returned for both the service and the staff grouping.
     */
    private static function service(array $statuses, bool $allowed, array $days = [], array $ids = []): ReportService
    {
        $query = new class ($statuses, $days, $ids) implements ReportQuery {
            /**
             * @param list<ReportRow> $statuses
             * @param list<ReportRow> $days
             * @param list<ReportRow> $ids
             */
            public function __construct(
                private readonly array $statuses,
                private readonly array $days,
                private readonly array $ids,
            ) {
            }

            /**
             * @return list<ReportRow>
             */
            public function byStatus(LocalDate $from, LocalDate $to, ?int $locationId): array
            {
                return $this->statuses;
            }

            /**
             * @return list<ReportRow>
             */
            public function byDay(LocalDate $from, LocalDate $to, ?int $locationId): array
            {
                return $this->days;
            }

            /**
             * @return list<ReportRow>
             */
            public function byService(LocalDate $from, LocalDate $to, ?int $locationId): array
            {
                return $this->ids;
            }

            /**
             * @return list<ReportRow>
             */
            public function byStaff(LocalDate $from, LocalDate $to, ?int $locationId): array
            {
                return $this->ids;
            }
        };
        $authorizer = new class ($allowed) implements Authorizer {
            public function __construct(private readonly bool $allowed)
            {
            }

            public function allows(string $capability): bool
            {
                return $this->allowed && BookingService::CAPABILITY === $capability;
            }
        };

        return new ReportService($query, $authorizer);
    }
}
