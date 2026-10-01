<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Application\WaitlistService;
use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistEntry;
use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistStatus;
use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Modules\Customers\Contracts\CustomerDirectory;
use Vaqtyar\Modules\Customers\Contracts\CustomerSummary;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Modules\Scheduling\Contracts\FreeStarts;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Tests\Fixtures\FixedClock;

/**
 * The waiting list: asking to be told about a full day, and the check that tells whoever waits once
 * a time opens.
 */
final class WaitlistServiceTest extends TestCase
{
    private const PAGE = 'https://site.test/book/';
    private const OPENED = 1_800_050_400;

    private MemoryWaitlist $waitlist;

    private RecordingWaitlistNotifier $notifier;

    private FixedClock $clock;

    private bool $admin = true;

    /** @var array<string, list<int>|null> starts by "variant/location/staff/date"; null cannot be booked. */
    private array $starts = [];

    private int $customerId = 7;

    protected function setUp(): void
    {
        $this->waitlist = new MemoryWaitlist();
        $this->notifier = new RecordingWaitlistNotifier();
    }

    public function testAGuestWaitsForAFullDay(): void
    {
        $query = new AvailabilityQuery(3, 1);
        $id = $this->service()->join('09120000000', 'Sara', '', null, $query, $this->day(2), self::PAGE);

        self::assertSame(1, $id);
        self::assertSame(WaitlistStatus::Waiting, $this->waitlist->entries[1]->status);
        self::assertSame($this->customerId, $this->waitlist->entries[1]->customerId);
    }

    public function testAskingTwiceIsOneRequest(): void
    {
        $query = new AvailabilityQuery(3, 1);
        $first = $this->service()->join('09120000000', '', '', null, $query, $this->day(2), self::PAGE);
        $again = $this->service()->join('09120000000', '', '', null, $query, $this->day(2), self::PAGE);

        self::assertSame($first, $again);
        self::assertCount(1, $this->waitlist->entries);
    }

    public function testADayWithATimeIsNotWaitedFor(): void
    {
        $this->starts['3/1/0/' . $this->day(2)->toString()] = [self::OPENED];

        try {
            $this->service()->join('09120000000', '', '', null, new AvailabilityQuery(3, 1), $this->day(2), self::PAGE);
            self::fail('A day with a time to book has no waiting list.');
        } catch (Conflict $e) {
            self::assertSame('day_not_full', $e->errorCode);
        }
        self::assertSame([], $this->waitlist->entries);
    }

    public function testAPastDayOrOneTooFarAheadIsRefused(): void
    {
        $query = new AvailabilityQuery(3, 1);
        foreach ([-1, WaitlistService::MAX_DAYS_AHEAD + 1] as $offset) {
            try {
                $this->service()->join('09120000000', '', '', null, $query, $this->day($offset), self::PAGE);
                self::fail('The day is outside what can be waited for.');
            } catch (InvalidValue $e) {
                self::assertSame('invalid_waitlist_date', $e->errorCode);
            }
        }
    }

    public function testACustomerWaitsOnFewDays(): void
    {
        $service = $this->service();
        for ($day = 0; $day < WaitlistService::MAX_WAITING_PER_CUSTOMER; ++$day) {
            $service->join('09120000000', '', '', null, new AvailabilityQuery(3, 1), $this->day($day), self::PAGE);
        }

        $this->expectException(Conflict::class);
        $service->join('09120000000', '', '', null, new AvailabilityQuery(3, 1), $this->day(9), self::PAGE);
    }

    public function testAServiceThatCannotBeBookedIsNotFound(): void
    {
        $this->starts['3/1/0/' . $this->day(2)->toString()] = null;

        $this->expectException(NotFound::class);
        $this->service()->join('09120000000', '', '', null, new AvailabilityQuery(3, 1), $this->day(2), self::PAGE);
    }

    public function testThePageMustBeAnAddress(): void
    {
        $this->expectException(InvalidValue::class);
        new WaitlistEntry(null, 7, 1, 3, null, $this->day(2), 'javascript:alert(1)');
    }

    public function testACustomerIsToldOnceWhenATimeOpens(): void
    {
        $service = $this->service();
        $service->join('09120000000', '', '', null, new AvailabilityQuery(3, 1), $this->day(2), self::PAGE);

        self::assertSame(0, $service->check());
        self::assertSame([], $this->notifier->told);

        $this->starts['3/1/0/' . $this->day(2)->toString()] = [self::OPENED, self::OPENED + 3600];
        self::assertSame(1, $service->check());
        self::assertSame(['7/' . $this->day(2)->toString() . '/' . self::OPENED], $this->notifier->told);
        self::assertSame(WaitlistStatus::Notified, $this->waitlist->entries[1]->status);

        self::assertSame(0, $service->check(), 'Nobody is told twice.');
        self::assertCount(1, $this->notifier->told);
    }

    public function testEveryoneWaitingForTheDayIsToldWhenItOpens(): void
    {
        $service = $this->service();
        $query = new AvailabilityQuery(3, 1);
        $service->join('09120000000', '', '', null, $query, $this->day(2), self::PAGE);
        $this->customerId = 8;
        $service->join('09130000000', '', '', null, $query, $this->day(2), self::PAGE);
        $this->starts['3/1/0/' . $this->day(2)->toString()] = [self::OPENED];

        self::assertSame(2, $service->check());
        self::assertCount(2, $this->notifier->told);
    }

    public function testADayThatPassedWithoutATimeExpires(): void
    {
        $service = $this->service();
        $service->join('09120000000', '', '', null, new AvailabilityQuery(3, 1), $this->day(0), self::PAGE);
        $this->clock->advance(2 * 86_400);

        self::assertSame(0, $service->check());
        self::assertSame(WaitlistStatus::Expired, $this->waitlist->entries[1]->status);
    }

    public function testARemovedServiceIsSkippedNotFatal(): void
    {
        $service = $this->service();
        $service->join('09120000000', '', '', null, new AvailabilityQuery(3, 1), $this->day(2), self::PAGE);
        $this->starts['3/1/0/' . $this->day(2)->toString()] = null;

        self::assertSame(0, $service->check());
        self::assertSame(WaitlistStatus::Waiting, $this->waitlist->entries[1]->status);
    }

    public function testTheAdminSeesWhoWaits(): void
    {
        $this->service()->join('09120000000', '', '', null, new AvailabilityQuery(3, 1), $this->day(2), self::PAGE);

        $page = $this->service()->page(0, 20);

        self::assertSame(1, $page->total);
        self::assertSame('Sara', $page->items[0]->customer?->name);
    }

    public function testOnlyStaffManageTheList(): void
    {
        $this->admin = false;

        $this->expectException(Forbidden::class);
        $this->service()->page(0, 20);
    }

    public function testStaffRemoveARequest(): void
    {
        $this->service()->join('09120000000', '', '', null, new AvailabilityQuery(3, 1), $this->day(2), self::PAGE);

        $this->service()->remove(1);

        self::assertSame([], $this->waitlist->entries);
    }

    private function day(int $offset): LocalDate
    {
        return LocalDate::fromString('2027-01-15')->addDays($offset);
    }

    private function service(): WaitlistService
    {
        $this->clock ??= new FixedClock('2027-01-15 08:00:00');

        return new WaitlistService(
            new class ($this->admin) implements Authorizer {
                public function __construct(private readonly bool $admin)
                {
                }

                public function allows(string $capability): bool
                {
                    return $this->admin;
                }
            },
            new class ($this->customerId) implements CustomerApi {
                public function __construct(private int &$id)
                {
                }

                public function canBook(int $customerId): bool
                {
                    return true;
                }

                public function forBooking(
                    string $phone,
                    string $firstName,
                    string $lastName,
                    ?string $email,
                    ?string $sessionToken = null,
                ): int {
                    return $this->id;
                }

                public function customerOfSession(?string $sessionToken): ?int
                {
                    return null;
                }
            },
            new class implements CustomerDirectory {
                /**
                 * @param list<int> $ids
                 * @return array<int, CustomerSummary>
                 */
                public function summaries(array $ids): array
                {
                    $found = [];
                    foreach ($ids as $id) {
                        $found[$id] = new CustomerSummary($id, 'Sara', '+989120000000', false);
                    }

                    return $found;
                }

                /**
                 * @return list<int>
                 */
                public function matching(string $query, int $limit): array
                {
                    return [];
                }
            },
            new class ($this->starts) implements FreeStarts {
                /**
                 * @param array<string, list<int>|null> $starts null is a service that cannot be booked.
                 */
                public function __construct(private array &$starts)
                {
                }

                /**
                 * @return list<int>
                 */
                public function startsOn(AvailabilityQuery $query, LocalDate $date): array
                {
                    $key = \implode(
                        '/',
                        [$query->variantId, $query->locationId, $query->staffId ?? 0, $date->toString()]
                    );
                    if (\array_key_exists($key, $this->starts) && null === $this->starts[$key]) {
                        throw new NotFound('service_not_found', 'No such service.');
                    }

                    return $this->starts[$key] ?? [];
                }
            },
            $this->waitlist,
            $this->notifier,
            $this->clock
        );
    }
}
