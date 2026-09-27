<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Application\AppointmentBrowser;
use Vaqtyar\Modules\Booking\Application\AppointmentDetail;
use Vaqtyar\Modules\Booking\Application\AppointmentFilter;
use Vaqtyar\Modules\Booking\Application\AppointmentQuery;
use Vaqtyar\Modules\Booking\Application\AppointmentRow;
use Vaqtyar\Modules\Booking\Application\AppointmentSearch;
use Vaqtyar\Modules\Booking\Application\AppointmentSort;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\PaymentStatus;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Modules\Customers\Contracts\CustomerDirectory;
use Vaqtyar\Modules\Customers\Contracts\CustomerSummary;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\NotFound;

/**
 * The admin's reads over appointments: the capability, what a typed search
 * becomes, the calendar's range, and customers named with one lookup. The
 * SQL is the integration suite's.
 */
final class AppointmentBrowserTest extends TestCase
{
    private const DAY = 86_400;

    public bool $allowed = true;

    /** @var list<AppointmentRow> */
    public array $rows = [];

    /** @var list<array{string, mixed}> */
    public array $calls = [];

    /** @var list<string> */
    public array $matchedQueries = [];

    /** @var list<list<int>> */
    public array $namedIds = [];

    private AppointmentBrowser $browser;

    protected function setUp(): void
    {
        parent::setUp();
        $test = $this;
        $query = new class ($test) implements AppointmentQuery {
            public function __construct(private readonly AppointmentBrowserTest $test)
            {
            }

            /**
             * @return list<AppointmentRow>
             */
            public function list(
                AppointmentFilter $filter,
                ?AppointmentSearch $search,
                AppointmentSort $sort,
                int $offset,
                int $limit,
            ): array {
                $this->test->calls[] = ['list', [$filter, $search, $sort, $offset, $limit]];

                return $this->test->rows;
            }

            public function count(AppointmentFilter $filter, ?AppointmentSearch $search): int
            {
                $this->test->calls[] = ['count', $search];

                return 57;
            }

            public function detail(int $id): ?AppointmentDetail
            {
                $this->test->calls[] = ['detail', $id];
                if (7 !== $id) {
                    return null;
                }

                return new AppointmentDetail(
                    AppointmentBrowserTest::row(7, 3),
                    '01J00000000000000000000000',
                    'admin',
                    PriceQuote::empty(),
                    '',
                    '',
                    [],
                    [],
                    [],
                    null,
                    0,
                    null,
                    null
                );
            }

            /**
             * @param list<int> $staffIds
             * @param list<AppointmentStatus> $statuses
             * @return list<AppointmentRow>
             */
            public function between(
                int $from,
                int $to,
                array $staffIds,
                ?int $locationId,
                array $statuses,
                int $limit,
            ): array {
                $this->test->calls[] = ['between', [$from, $to, $staffIds, $locationId, $statuses, $limit]];

                return $this->test->rows;
            }
        };
        $customers = new class ($test) implements CustomerDirectory {
            public function __construct(private readonly AppointmentBrowserTest $test)
            {
            }

            /**
             * @param list<int> $ids
             * @return array<int, CustomerSummary>
             */
            public function summaries(array $ids): array
            {
                $this->test->namedIds[] = $ids;
                $known = [3 => new CustomerSummary(3, 'Ali Karimi', '+989121234567', false)];

                return \array_intersect_key($known, \array_flip($ids));
            }

            /**
             * @return list<int>
             */
            public function matching(string $query, int $limit): array
            {
                $this->test->matchedQueries[] = $query;

                return 'نیست' === $query ? [] : [3, 9];
            }
        };
        $this->browser = new AppointmentBrowser(
            $query,
            $customers,
            new class ($test) implements Authorizer {
                public function __construct(private readonly AppointmentBrowserTest $test)
                {
                }

                public function allows(string $capability): bool
                {
                    return $this->test->allowed && 'manage_bookings' === $capability;
                }
            }
        );
    }

    public function testEveryReadNeedsTheBookingsCapability(): void
    {
        $this->allowed = false;
        $refused = 0;
        foreach (
            [
                fn () => $this->browser->list(new AppointmentFilter(), '', AppointmentSort::StartDesc, 0, 20),
                fn () => $this->browser->appointment(7),
                fn () => $this->browser->calendar(0, self::DAY, [], null),
            ] as $read
        ) {
            try {
                $read();
            } catch (Forbidden) {
                ++$refused;
            }
        }

        self::assertSame([3, []], [$refused, $this->calls]);
    }

    public function testAPageNamesItsCustomersWithOneLookup(): void
    {
        $this->rows = [self::row(1, 3), self::row(2, 5), self::row(3, 3)];

        $page = $this->browser->list(new AppointmentFilter(), '  ', AppointmentSort::CreatedAsc, 40, 20);

        self::assertSame(57, $page->total);
        self::assertSame([[3, 5]], $this->namedIds);
        self::assertSame(
            ['Ali Karimi', null, 'Ali Karimi'],
            \array_map(static fn (AppointmentRow $row): ?string => $row->customer?->name, $page->items)
        );
        self::assertSame(
            [null, AppointmentSort::CreatedAsc, 40, 20],
            \array_slice(self::args($this->calls[0]), 1),
            'A blank search is no search.'
        );
        self::assertSame([], $this->matchedQueries);
    }

    public function testASearchLooksForATrackingCodeAndForCustomers(): void
    {
        $this->browser->list(new AppointmentFilter(), ' ab۱۲cd۳۴ ', AppointmentSort::StartDesc, 0, 20);
        $this->browser->list(new AppointmentFilter(), 'علی', AppointmentSort::StartDesc, 0, 20);

        $searches = \array_map(
            static fn (array $call): mixed => self::args($call)[1],
            \array_values(\array_filter($this->calls, static fn (array $call): bool => 'list' === $call[0]))
        );
        self::assertEquals(
            [new AppointmentSearch('AB12CD34', [3, 9]), new AppointmentSearch(null, [3, 9])],
            $searches
        );
        self::assertSame(['ab۱۲cd۳۴', 'علی'], $this->matchedQueries);
    }

    public function testASearchThatMatchesNothingDoesNotQuery(): void
    {
        $page = $this->browser->list(new AppointmentFilter(), 'نیست', AppointmentSort::StartDesc, 0, 20);

        self::assertSame([[], 0, []], [$page->items, $page->total, $this->calls]);
    }

    public function testTheDatesMustBeInOrder(): void
    {
        try {
            $this->browser->list(
                new AppointmentFilter(
                    from: LocalDate::fromString('2026-10-02'),
                    to: LocalDate::fromString('2026-10-01')
                ),
                '',
                AppointmentSort::StartDesc,
                0,
                20
            );
            self::fail('The range was taken.');
        } catch (InvalidValue $e) {
            self::assertSame(['invalid_range', []], [$e->errorCode, $this->calls]);
        }
    }

    public function testOneAppointmentIsFoundAndNamed(): void
    {
        self::assertSame('Ali Karimi', $this->browser->appointment(7)->row->customer?->name);

        try {
            $this->browser->appointment(8);
            self::fail('An unknown id was found.');
        } catch (NotFound $e) {
            self::assertSame('appointment_not_found', $e->errorCode);
        }
    }

    public function testTheCalendarShowsWhatTakesTimeInABoundedRange(): void
    {
        $this->rows = [self::row(1, 3)];

        $rows = $this->browser->calendar(1_000, 1_000 + 42 * self::DAY, [4, 4, 2], 6);

        self::assertSame('Ali Karimi', $rows[0]->customer?->name);
        self::assertSame(
            [1_000, 1_000 + 42 * self::DAY, [2, 4], 6, [
                AppointmentStatus::PendingApproval,
                AppointmentStatus::PendingPayment,
                AppointmentStatus::Confirmed,
                AppointmentStatus::Completed,
                AppointmentStatus::NoShow,
            ], AppointmentBrowser::MAX_CALENDAR_ITEMS + 1],
            self::args($this->calls[0])
        );
    }

    public function testTheCalendarRefusesARangeWithTooManyToShow(): void
    {
        $this->rows = \array_fill(0, AppointmentBrowser::MAX_CALENDAR_ITEMS + 1, self::row(1, 3));

        try {
            $this->browser->calendar(0, self::DAY, [], null);
            self::fail('The calendar took them all.');
        } catch (InvalidValue $e) {
            self::assertSame(['too_many_appointments', []], [$e->errorCode, $this->namedIds]);
        }
    }

    public function testTheCalendarRefusesAnEmptyOrLongRange(): void
    {
        $codes = [];
        foreach ([[5, 5], [5, 5 + 42 * self::DAY + 1]] as [$from, $to]) {
            try {
                $this->browser->calendar($from, $to, [], null);
            } catch (InvalidValue $e) {
                $codes[] = $e->errorCode;
            }
        }

        self::assertSame([['invalid_range', 'range_too_long'], []], [$codes, $this->calls]);
    }

    public static function row(int $id, int $customerId): AppointmentRow
    {
        return new AppointmentRow(
            $id,
            'AB12CD34',
            AppointmentStatus::Confirmed,
            PaymentStatus::Unpaid,
            $customerId,
            1,
            1,
            1,
            1,
            0,
            3_600,
            'Asia/Tehran',
            1,
            Money::ofRial(1_000_000)
        );
    }

    /**
     * @param array{string, mixed} $call
     * @return list<mixed>
     */
    private static function args(array $call): array
    {
        return \is_array($call[1]) ? \array_values($call[1]) : [$call[1]];
    }
}
