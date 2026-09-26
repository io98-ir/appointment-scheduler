<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Scheduling\Application;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Catalog\Contracts\ExtraOffer;
use Vaqtyar\Modules\Catalog\Contracts\LocationInfo;
use Vaqtyar\Modules\Catalog\Contracts\Offer;
use Vaqtyar\Modules\Catalog\Contracts\ResourceNeed;
use Vaqtyar\Modules\Catalog\Contracts\ResourceUnit;
use Vaqtyar\Modules\Catalog\Contracts\StaffOffer;
use Vaqtyar\Modules\Scheduling\Application\Availability;
use Vaqtyar\Modules\Scheduling\Application\AvailabilityDefaults;
use Vaqtyar\Modules\Scheduling\Application\AvailabilityQuery;
use Vaqtyar\Modules\Scheduling\Application\AvailabilityService;
use Vaqtyar\Modules\Scheduling\Application\DayAvailability;
use Vaqtyar\Modules\Scheduling\Application\DayStatus;
use Vaqtyar\Modules\Scheduling\Application\SlotCache;
use Vaqtyar\Modules\Scheduling\Contracts\BusySpan;
use Vaqtyar\Modules\Scheduling\Contracts\OccupancyReader;
use Vaqtyar\Modules\Scheduling\Domain\Availability\StaffChoice;
use Vaqtyar\Modules\Scheduling\Domain\ExceptionKind;
use Vaqtyar\Modules\Scheduling\Domain\Holiday;
use Vaqtyar\Modules\Scheduling\Domain\HolidayRepository;
use Vaqtyar\Modules\Scheduling\Domain\HolidaySource;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\OwnerType;
use Vaqtyar\Modules\Scheduling\Domain\RuleKind;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleException;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleExceptionRepository;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRuleRepository;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\Slug;
use Vaqtyar\Tests\Fixtures\FixedClock;

/**
 * Availability on stored schedules and bookings, in Tehran (UTC+03:30).
 * 2026-10-03 is a Saturday (weekday 0); staff member 3 works it 09:00-12:00
 * at location 1, with 60-minute bookings on a 60-minute grid.
 */
final class AvailabilityServiceTest extends TestCase
{
    private const VARIANT = 5;
    private const LOCATION = 1;
    private const SATURDAY = '2026-10-03';

    private CatalogApi&MockInterface $catalog;

    /** @var list<ScheduleRule> */
    private array $rules = [];

    /** @var list<ScheduleException> */
    private array $exceptions = [];

    /** @var list<Holiday> */
    private array $holidays = [];

    /** @var list<BusySpan> */
    private array $busy = [];

    /** @var list<array{list<int>, list<int>, int, int}> */
    private array $reads = [];

    private int $ruleReads = 0;

    private FixedClock $clock;

    private Offer $offer;

    private ?string $calendar = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Friday 2026-10-02 09:00 in Tehran.
        $this->clock = new FixedClock('2026-10-02 05:30:00');
        $this->offer = self::offer();
        /** @var CatalogApi&MockInterface $catalog Mockery has no PHPStan extension here. */
        $catalog = Mockery::mock(CatalogApi::class);
        $this->catalog = $catalog;
        $this->catalog->shouldReceive('offer')->andReturnUsing(
            fn (int $id): ?Offer => self::VARIANT === $id ? $this->offer : null
        );
        $this->catalog->shouldReceive('location')->andReturnUsing(
            fn (int $id): ?LocationInfo => self::LOCATION === $id
                ? new LocationInfo($id, new \DateTimeZone('Asia/Tehran'), $this->calendar)
                : null
        );
        $this->rules = [
            self::rule(OwnerType::Staff, 3, 0, '09:00', '12:00'),
            // Staff member 4 works at location 2.
            self::rule(OwnerType::Staff, 4, 0, '09:00', '12:00'),
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testADayOffersTheStartsOfItsStaffAtTheLocation(): void
    {
        $day = $this->day();

        self::assertSame(DayStatus::Available, $day->status);
        self::assertSame(['09:00', '10:00', '11:00'], self::starts($day));
        self::assertSame([[3], [3], [3]], \array_map(static fn ($slot): array => $slot->staffIds(), $day->slots));
        self::assertSame(
            [[[3], [], self::utc('2026-10-03 00:00'), self::utc('2026-10-04 01:00')]],
            $this->reads,
            'One read for the day, the grid widened by the longest booking.'
        );
    }

    public function testTheLocationHoursBoundTheStaffHours(): void
    {
        $this->rules[] = self::rule(OwnerType::Location, self::LOCATION, 0, '10:00', '24:00');

        self::assertSame(['10:00', '11:00'], self::starts($this->day()));
    }

    public function testAHolidayOfTheLocationCalendarClosesTheDay(): void
    {
        $this->calendar = 'ir';
        $this->holidays = [new Holiday(
            Slug::fromInput('ir'),
            LocalDate::fromString(self::SATURDAY),
            Name::fromInput('A'),
            HolidaySource::Dataset
        )];

        self::assertSame(DayStatus::Closed, $this->day()->status);
    }

    public function testABookingOrABlockTakesTheStaffTime(): void
    {
        $this->busy = [self::busy(false, 3, '10:00', '11:00')];
        $this->exceptions = [
            new ScheduleException(
                null,
                new Owner(OwnerType::Staff, 3),
                LocalDate::fromString(self::SATURDAY),
                LocalTime::fromString('11:00'),
                LocalTime::fromString('12:00'),
                ExceptionKind::Blocked,
                ''
            ),
        ];

        self::assertSame(['09:00'], self::starts($this->day()));
    }

    public function testADayWorkedButTakenIsFull(): void
    {
        $this->busy = [self::busy(false, 3, '09:00', '12:00')];

        $day = $this->day();

        self::assertSame([DayStatus::Full, []], [$day->status, $day->slots]);
    }

    public function testExtrasLengthenTheBookingByTheirUnits(): void
    {
        // Two units of 15 minutes: 90 minutes no longer fit at 11:00.
        $day = $this->day(new AvailabilityQuery(self::VARIANT, self::LOCATION, extraIds: [40, 40]));

        self::assertSame(['09:00', '10:00'], self::starts($day));
    }

    /**
     * @return iterable<string, array{AvailabilityQuery, class-string<\Throwable>, string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'unknown variant' => [new AvailabilityQuery(99, self::LOCATION), NotFound::class, 'variant_not_found'];
        yield 'unknown location' => [new AvailabilityQuery(self::VARIANT, 99), NotFound::class, 'location_not_found'];
        yield 'extra of another service' => [
            new AvailabilityQuery(self::VARIANT, self::LOCATION, extraIds: [99]),
            InvalidValue::class,
            'invalid_extra',
        ];
        yield 'more units than allowed' => [
            new AvailabilityQuery(self::VARIANT, self::LOCATION, extraIds: [40, 40, 40]),
            InvalidValue::class,
            'invalid_extra',
        ];
        yield 'party over the capacity' => [
            new AvailabilityQuery(self::VARIANT, self::LOCATION, partySize: 2),
            InvalidValue::class,
            'party_too_large',
        ];
        yield 'staff member who does not serve it' => [
            new AvailabilityQuery(self::VARIANT, self::LOCATION, staffId: 9),
            InvalidValue::class,
            'unknown_staff',
        ];
        yield 'staff member of another location' => [
            new AvailabilityQuery(self::VARIANT, self::LOCATION, staffId: 4),
            InvalidValue::class,
            'unknown_staff',
        ];
    }

    /**
     * @dataProvider invalidQueries
     * @param class-string<\Throwable> $exception
     */
    public function testAnInvalidQueryIsRefused(AvailabilityQuery $query, string $exception, string $code): void
    {
        try {
            $this->service()->day($query, LocalDate::fromString(self::SATURDAY));
            self::fail('Expected ' . $exception);
        } catch (NotFound | InvalidValue $e) {
            self::assertSame([$exception, $code], [$e::class, $e->errorCode]);
        }
    }

    public function testAChosenStaffMemberIsTheOnlyCandidate(): void
    {
        $this->offer = self::offer(staff: [
            new StaffOffer(3, self::LOCATION, 60, Money::ofRial(1)),
            new StaffOffer(6, null, 60, Money::ofRial(1)),
        ]);
        $this->rules[] = self::rule(OwnerType::Staff, 6, 0, '09:00', '10:00');

        $any = $this->day();
        $chosen = $this->day(new AvailabilityQuery(self::VARIANT, self::LOCATION, staffId: 6));

        self::assertSame([3, 6], $any->slots[0]->staffIds());
        self::assertSame([['09:00'], [6]], [self::starts($chosen), $chosen->slots[0]->staffIds()]);
    }

    public function testResourcesOfAnotherLocationAreLeftOut(): void
    {
        $this->offer = self::offer(resources: [new ResourceNeed('room', 1, [
            new ResourceUnit(20, 2, 1),
            new ResourceUnit(21, null, 1),
        ])]);
        $this->rules[] = self::rule(OwnerType::Resource, 21, 0, '10:00', '12:00');
        $this->rules[] = self::rule(OwnerType::Resource, 20, 0, '09:00', '12:00');

        self::assertSame(['10:00', '11:00'], self::starts($this->day()));
        self::assertSame([[3], [21]], [$this->reads[0][0], $this->reads[0][1]]);
    }

    public function testAResourceWithoutAWeeklyScheduleIsOpenWheneverTheLocationIs(): void
    {
        $this->offer = self::offer(resources: [new ResourceNeed('room', 1, [new ResourceUnit(21, null, 1)])]);
        $this->busy = [self::busy(true, 21, '10:00', '11:00')];

        self::assertSame(['09:00', '11:00'], self::starts($this->day()));
    }

    public function testWeeklyRulesAreReadOncePerRequest(): void
    {
        $service = $this->service();
        $rules = $this->ruleReads;

        $service->month(self::query(), LocalDate::fromString(self::SATURDAY), 21);

        self::assertSame(1, $this->ruleReads - $rules);
        self::assertCount(3, $this->reads);
    }

    public function testTheVariantStepWinsOverTheSiteStep(): void
    {
        $this->offer = self::offer(stepMin: 90);

        self::assertSame(['09:00', '10:30'], self::starts($this->day()));
    }

    public function testStartsBeforeTheMinimumNoticeAreLeftOut(): void
    {
        // Saturday 09:30 in Tehran; with 60 minutes' notice 10:30 is the earliest.
        $this->clock = new FixedClock('2026-10-03 06:00:00');

        self::assertSame(['11:00'], self::starts($this->day()));
    }

    public function testAMonthGivesEachDayItsStatus(): void
    {
        $this->busy = [self::busy(false, 3, '09:00', '12:00', '2026-10-10')];
        // The window ends 3 days out, on Monday 2026-10-05 09:00.
        $service = $this->service(new AvailabilityDefaults(60, 60, 3 * 1440));

        $month = $service->month(self::query(), LocalDate::fromString('2026-10-01'), 10);

        self::assertSame(
            [
                '2026-10-01' => 'closed',
                '2026-10-02' => 'closed',
                '2026-10-03' => 'available',
                '2026-10-04' => 'closed',
                '2026-10-05' => 'closed',
                '2026-10-06' => 'closed',
                '2026-10-07' => 'closed',
                '2026-10-08' => 'closed',
                '2026-10-09' => 'closed',
                '2026-10-10' => 'closed',
            ],
            self::statuses($month)
        );
        self::assertSame('Asia/Tehran', $month->timezone->getName());
    }

    public function testAMonthTellsFullFromClosedWithinTheWindow(): void
    {
        $this->busy = [self::busy(false, 3, '09:00', '12:00', '2026-10-10')];

        $month = $this->service()->month(self::query(), LocalDate::fromString('2026-10-09'), 3);

        self::assertSame(
            ['2026-10-09' => 'closed', '2026-10-10' => 'full', '2026-10-11' => 'closed'],
            self::statuses($month)
        );
    }

    public function testTheFirstViewFindsTheNextDayWithAFreeStart(): void
    {
        $this->busy = [self::busy(false, 3, '09:00', '12:00')];

        $first = $this->service()->first(self::query(), LocalDate::fromString('2026-10-03'), 30);

        self::assertCount(1, $first->days);
        self::assertSame(['2026-10-10', ['09:00', '10:00', '11:00']], [
            $first->days[0]->date->toString(),
            self::starts($first->days[0]),
        ]);
        self::assertCount(2, $this->reads, 'Read a week at a time, and stopped at the first.');
    }

    public function testTheFirstViewIsEmptyWhenNothingIsFreeWithinTheDays(): void
    {
        $first = $this->service()->first(self::query(), LocalDate::fromString('2026-10-04'), 6);

        self::assertSame([], $first->days);
    }

    public function testADayWithoutADateIsTodayAtTheLocation(): void
    {
        // Friday 23:00 UTC is Saturday 02:30 in Tehran.
        $this->clock = new FixedClock('2026-10-02 23:00:00');

        $availability = $this->service()->day(self::query(), null);

        self::assertSame(self::SATURDAY, $availability->days[0]->date->toString());
    }

    public function testAComputedDayIsCachedAndStillFilteredByTheClock(): void
    {
        $service = $this->service();
        $service->day(self::query(), LocalDate::fromString(self::SATURDAY));
        $this->busy = [self::busy(false, 3, '09:00', '12:00')];
        // Saturday 10:00 in Tehran: with an hour's notice only 11:00 is left.
        $this->clock->advance(86_400 + 3_600);

        $again = $service->day(self::query(), LocalDate::fromString(self::SATURDAY));

        self::assertSame(['11:00'], self::starts($again->days[0]));
        self::assertCount(1, $this->reads, 'The second request is served from the cache.');
    }

    public function testTheCacheKeyTellsQueriesApart(): void
    {
        $service = $this->service();
        $service->day(self::query(), LocalDate::fromString(self::SATURDAY));
        $service->day(
            new AvailabilityQuery(self::VARIANT, self::LOCATION, extraIds: [40]),
            LocalDate::fromString(self::SATURDAY)
        );

        self::assertCount(2, $this->reads);
    }

    private function day(?AvailabilityQuery $query = null): DayAvailability
    {
        return $this->service()->day($query ?? self::query(), LocalDate::fromString(self::SATURDAY))->days[0];
    }

    private function service(?AvailabilityDefaults $defaults = null): AvailabilityService
    {
        /** @var ScheduleRuleRepository&MockInterface $rules */
        $rules = Mockery::mock(ScheduleRuleRepository::class);
        $rules->shouldReceive('ofOwners')->andReturnUsing(function (array $owners): array {
            ++$this->ruleReads;

            return \array_values(\array_filter(
                $this->rules,
                static fn (ScheduleRule $rule): bool => self::isOneOf($rule->owner, $owners)
            ));
        });
        /** @var ScheduleExceptionRepository&MockInterface $exceptions */
        $exceptions = Mockery::mock(ScheduleExceptionRepository::class);
        $exceptions->shouldReceive('between')->andReturnUsing(
            fn (array $owners): array => \array_values(\array_filter(
                $this->exceptions,
                static fn (ScheduleException $exception): bool => self::isOneOf($exception->owner, $owners)
            ))
        );
        /** @var HolidayRepository&MockInterface $holidays */
        $holidays = Mockery::mock(HolidayRepository::class);
        $holidays->shouldReceive('between')->andReturnUsing(fn (): array => $this->holidays);

        $reader = new class ($this) implements OccupancyReader {
            public function __construct(private readonly AvailabilityServiceTest $test)
            {
            }

            /**
             * @param list<int> $staffIds
             * @param list<int> $resourceIds
             * @return list<BusySpan>
             */
            public function overlapping(array $staffIds, array $resourceIds, int $from, int $to): array
            {
                return $this->test->read($staffIds, $resourceIds, $from, $to);
            }
        };
        $cache = new class () implements SlotCache {
            /** @var array<string, array<mixed>> */
            private array $store = [];

            /**
             * @return ?array<mixed>
             */
            public function get(string $key): ?array
            {
                return $this->store[$key] ?? null;
            }

            /**
             * @param array<mixed> $value
             */
            public function set(string $key, array $value): void
            {
                $this->store[$key] = $value;
            }
        };

        return new AvailabilityService(
            $this->catalog,
            $rules,
            $exceptions,
            $holidays,
            $reader,
            $cache,
            $this->clock,
            $defaults ?? new AvailabilityDefaults(60, 60, 30 * 1440, StaffChoice::LeastBusy)
        );
    }

    /**
     * The fake OccupancyReader: what is busy, and each read it served.
     *
     * @param list<int> $staffIds
     * @param list<int> $resourceIds
     * @return list<BusySpan>
     */
    public function read(array $staffIds, array $resourceIds, int $from, int $to): array
    {
        $this->reads[] = [$staffIds, $resourceIds, $from, $to];

        return \array_values(\array_filter(
            $this->busy,
            static fn (BusySpan $span): bool => $span->start < $to && $span->end > $from
                && \in_array($span->ownerId, $span->onResource ? $resourceIds : $staffIds, true)
        ));
    }

    /**
     * @param array<Owner> $owners
     */
    private static function isOneOf(Owner $owner, array $owners): bool
    {
        foreach ($owners as $candidate) {
            if ($candidate->equals($owner)) {
                return true;
            }
        }

        return false;
    }

    private static function query(): AvailabilityQuery
    {
        return new AvailabilityQuery(self::VARIANT, self::LOCATION);
    }

    /**
     * @param ?list<StaffOffer> $staff
     * @param list<ResourceNeed> $resources
     */
    private static function offer(?array $staff = null, array $resources = [], ?int $stepMin = null): Offer
    {
        return new Offer(
            7,
            self::VARIANT,
            1,
            0,
            0,
            $stepMin,
            $staff ?? [
                new StaffOffer(3, self::LOCATION, 60, Money::ofRial(1_000_000)),
                new StaffOffer(4, 2, 60, Money::ofRial(1_000_000)),
            ],
            $resources,
            [new ExtraOffer(40, 15, Money::ofRial(100_000), 2)]
        );
    }

    private static function rule(OwnerType $type, int $id, int $weekday, string $start, string $end): ScheduleRule
    {
        return new ScheduleRule(
            null,
            new Owner($type, $id),
            $weekday,
            LocalTime::fromString($start),
            LocalTime::fromString($end),
            RuleKind::Work
        );
    }

    private static function busy(
        bool $onResource,
        int $ownerId,
        string $start,
        string $end,
        string $date = self::SATURDAY,
    ): BusySpan {
        return new BusySpan(
            $onResource,
            $ownerId,
            self::utc("{$date} {$start}"),
            self::utc("{$date} {$end}"),
            1,
            self::VARIANT,
            $ownerId
        );
    }

    /**
     * @param string $tehran A Tehran wall-clock time, "Y-m-d H:i".
     */
    private static function utc(string $tehran): int
    {
        return (new \DateTimeImmutable($tehran, new \DateTimeZone('Asia/Tehran')))->getTimestamp();
    }

    /**
     * @return list<string> Tehran wall-clock times.
     */
    private static function starts(DayAvailability $day): array
    {
        return \array_map(
            static fn ($slot): string => (new \DateTimeImmutable('@' . $slot->start))
                ->setTimezone(new \DateTimeZone('Asia/Tehran'))
                ->format('H:i'),
            $day->slots
        );
    }

    /**
     * @return array<string, string>
     */
    private static function statuses(Availability $availability): array
    {
        $statuses = [];
        foreach ($availability->days as $day) {
            $statuses[$day->date->toString()] = $day->status->value;
        }

        return $statuses;
    }
}
