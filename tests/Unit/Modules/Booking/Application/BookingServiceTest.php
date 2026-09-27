<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Application\Actor;
use Vaqtyar\Modules\Booking\Application\AppointmentRepository;
use Vaqtyar\Modules\Booking\Application\BookingJobs;
use Vaqtyar\Modules\Booking\Application\BookingService;
use Vaqtyar\Modules\Booking\Application\FieldReader;
use Vaqtyar\Modules\Booking\Application\HeldBooking;
use Vaqtyar\Modules\Booking\Application\HoldRepository;
use Vaqtyar\Modules\Booking\Application\PricingReader;
use Vaqtyar\Modules\Booking\Application\ResourceLocker;
use Vaqtyar\Modules\Booking\Application\StoredAppointment;
use Vaqtyar\Modules\Booking\Application\StoredHold;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\StatusChange;
use Vaqtyar\Modules\Booking\Domain\Field\Field;
use Vaqtyar\Modules\Booking\Domain\Field\FieldType;
use Vaqtyar\Modules\Booking\Domain\Hold;
use Vaqtyar\Modules\Booking\Domain\HoldToken;
use Vaqtyar\Modules\Booking\Domain\Pricing\Coupon;
use Vaqtyar\Modules\Booking\Domain\Pricing\CouponType;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceLine;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Catalog\Contracts\LocationInfo;
use Vaqtyar\Modules\Catalog\Contracts\Offer;
use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Modules\Scheduling\Contracts\Claim;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Tests\Fixtures\FixedClock;

/**
 * Confirming a hold (booking-engine §3): the hold's locks first in the
 * transaction, the hold read again under them, the coupon's use counted,
 * the appointment written, the occupancies handed over and the job queued
 * before the commit; the cache told after it.
 */
final class BookingServiceTest extends TestCase
{
    /** 2027-01-15 12:00 in Tehran. */
    private const START = 1_800_001_800;

    /** @var list<string> */
    public array $log = [];

    public ?StoredHold $hold;

    public ?Coupon $coupon = null;

    public bool $allowed = true;

    public bool $customerCanBook = true;

    public ?Appointment $added = null;

    /** @var list<Field> */
    public array $fields = [];

    /** @var array<string, string> */
    public array $savedAnswers = [];

    private FixedClock $clock;

    private HoldToken $token;

    private PriceQuote $quote;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FixedClock('@1800000000');
        $this->token = HoldToken::generate();
        $this->hold = new StoredHold(
            12,
            ['res:21', 'staff:3'],
            self::START - 600,
            self::START + 4200,
            1_799_999_900,
            1_800_000_500
        );
        $this->quote = PriceQuote::empty()->with(new PriceLine(PriceLine::BASE, Money::ofRial(1_200_000)));
    }

    public function testConfirmLocksRereadsWritesHandsOverAndQueuesInOneTransaction(): void
    {
        $booked = $this->service()->confirm($this->token, 9, 'Window seat', 2);

        self::assertSame(
            [
                'find',
                'begin',
                'lock res:21,staff:3 ' . (self::START - 600) . '-' . (self::START + 4200),
                'find for update',
                'details 12',
                'add admin 2 created',
                'hand over 12 to 77',
                'job 77',
                'commit',
                'changed',
            ],
            $this->log
        );
        $appointment = $booked->appointment;
        self::assertSame(77, $booked->id);
        self::assertSame(AppointmentStatus::Confirmed, $appointment->status());
        self::assertSame(
            [9, 1, 7, 5, 3, self::START, self::START + 3600, '2027-01-15', 'Asia/Tehran', 2, 'Window seat'],
            [
                $appointment->customerId,
                $appointment->locationId,
                $appointment->serviceId,
                $appointment->variantId,
                $appointment->staffId,
                $appointment->start,
                $appointment->end,
                $appointment->localDate,
                $appointment->timezone,
                $appointment->partySize,
                $appointment->customerNote,
            ]
        );
        self::assertSame(1_200_000, $appointment->quote->total()->amount);
    }

    public function testACouponIsCheckedAgainAndCountedUnderItsLock(): void
    {
        $this->quote = $this->quote->with(new PriceLine(PriceLine::COUPON, Money::ofRial(-120_000), 8));
        $this->coupon = new Coupon(8, 'NOWRUZ', CouponType::Percent, 10, true, null, null, 5, 4);

        $this->service()->confirm($this->token, 9, '', 2);

        self::assertSame(['details 12', 'coupon 8', 'count 8', 'add admin 2 created'], \array_slice($this->log, 4, 4));
    }

    public function testACouponUsedUpSinceTheHoldRefusesTheBooking(): void
    {
        $this->quote = $this->quote->with(new PriceLine(PriceLine::COUPON, Money::ofRial(-120_000), 8));
        $this->coupon = new Coupon(8, 'NOWRUZ', CouponType::Percent, 10, true, null, null, 5, 5);

        try {
            $this->service()->confirm($this->token, 9, '', 2);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('coupon_used_up', $e->errorCode);
        }
        self::assertNull($this->added);
        self::assertContains('rollback', $this->log);
        self::assertNotContains('changed', $this->log);
    }

    public function testValidAnswersAreSavedAfterTheAppointment(): void
    {
        $this->fields = [new Field('note', FieldType::Text, 'Note', true, [], null, 0)];

        $this->service()->confirm($this->token, 9, '', 2, ['note' => 'Window seat']);

        self::assertSame(['add admin 2 created', 'answers 77'], \array_slice($this->log, 5, 2));
        self::assertSame(['note' => 'Window seat'], $this->savedAnswers);
    }

    public function testAMissingRequiredAnswerRefusesTheBookingAndRollsBack(): void
    {
        $this->fields = [new Field('note', FieldType::Text, 'Note', true, [], null, 0)];

        try {
            $this->service()->confirm($this->token, 9, '', 2);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('answer_required', $e->errorCode);
        }
        self::assertNull($this->added);
        self::assertContains('rollback', $this->log);
        self::assertNotContains('changed', $this->log);
    }

    public function testAnExpiredHoldCannotBeConfirmed(): void
    {
        $this->clock->advance(500);

        $this->assertNotFound();
        self::assertSame(['find', 'begin', 'lock', 'find', 'rollback'], $this->steps());
    }

    /**
     * Two confirms of one token: the second waits on the locks, then finds
     * the hold gone.
     */
    public function testAHoldGoneUnderTheLocksCannotBeConfirmed(): void
    {
        $service = $this->service();
        $hold = $this->hold;
        $service->confirm($this->token, 9, '', 2);
        $this->log = [];
        $this->added = null;
        // The first read, before the locks, still saw it.
        $service = $this->service(5, $hold);

        $this->assertNotFound($service);
        self::assertSame(['find', 'begin', 'lock', 'find', 'rollback'], $this->steps());
        self::assertNull($this->added);
    }

    public function testAnUnknownTokenIsNotFound(): void
    {
        $this->hold = null;

        $this->assertNotFound();
        self::assertSame(['find'], $this->steps());
    }

    public function testAServiceGoneSinceTheHoldIsAConflict(): void
    {
        try {
            $this->service(6)->confirm($this->token, 9, '', 2);
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('service_unavailable', $e->errorCode);
        }
        self::assertNull($this->added);
    }

    public function testOnlyStaffWhoManageBookingsMayConfirm(): void
    {
        $this->allowed = false;

        try {
            $this->service()->confirm($this->token, 9, '', 2);
            self::fail('No exception.');
        } catch (Forbidden) {
        }
        self::assertSame([], $this->log);
    }

    public function testAnUnknownOrBlockedCustomerCannotBook(): void
    {
        $this->customerCanBook = false;

        try {
            $this->service()->confirm($this->token, 9, '', 2);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('customer_unavailable', $e->errorCode);
        }
        self::assertSame([], $this->log);
    }

    public function record(string $entry): void
    {
        $this->log[] = $entry;
    }

    private function assertNotFound(?BookingService $service = null): void
    {
        try {
            ($service ?? $this->service())->confirm($this->token, 9, '', 2);
            self::fail('No exception.');
        } catch (NotFound $e) {
            self::assertSame('hold_not_found', $e->errorCode);
        }
        self::assertNotContains('changed', $this->log);
    }

    /**
     * @return list<string>
     */
    private function steps(): array
    {
        return \array_map(static fn (string $entry): string => \explode(' ', $entry)[0], $this->log);
    }

    /**
     * @param ?StoredHold $staleRead what the read before the locks returns,
     *     when it differs from the one under them.
     */
    private function service(int $variantId = 5, ?StoredHold $staleRead = null): BookingService
    {
        $test = $this;
        $details = new HeldBooking(1, $variantId, 3, self::START, self::START + 3600, 2, [], $this->quote);

        $holds = new class ($test, $details, $staleRead) implements HoldRepository {
            public function __construct(
                private readonly BookingServiceTest $test,
                private readonly HeldBooking $details,
                private readonly ?StoredHold $staleRead,
            ) {
            }

            public function add(Hold $hold, string $tokenHash): int
            {
                throw new \LogicException('Not used.');
            }

            public function find(string $tokenHash, bool $forUpdate = false): ?StoredHold
            {
                $this->test->record($forUpdate ? 'find for update' : 'find');

                return $forUpdate ? $this->test->hold : ($this->staleRead ?? $this->test->hold);
            }

            public function details(int $id): HeldBooking
            {
                $this->test->record("details {$id}");

                return $this->details;
            }

            public function handOver(int $holdId, int $appointmentId): void
            {
                $this->test->record("hand over {$holdId} to {$appointmentId}");
                $this->test->hold = null;
            }

            public function extend(int $id, int $expiresAt): void
            {
            }

            public function purgeExpired(int $now, int $limit): int
            {
                return 0;
            }
        };
        $pricing = new class ($test) implements PricingReader {
            public function __construct(private readonly BookingServiceTest $test)
            {
            }

            /**
             * @return list<\Vaqtyar\Modules\Booking\Domain\Pricing\TimeRule>
             */
            public function timeRules(int $serviceId): array
            {
                return [];
            }

            public function coupon(string $code): ?Coupon
            {
                return null;
            }

            public function couponForUse(int $id): ?Coupon
            {
                $this->test->record("coupon {$id}");

                return $this->test->coupon;
            }

            public function countUse(int $id): void
            {
                $this->test->record("count {$id}");
            }
        };
        $appointments = new class ($test) implements AppointmentRepository {
            public function __construct(private readonly BookingServiceTest $test)
            {
            }

            public function add(
                Appointment $appointment,
                StatusChange $change,
                string $source,
                ?int $userId,
                int $now,
            ): int {
                $this->test->record("add {$source} {$userId} {$change->action}");
                $this->test->added = $appointment;

                return 77;
            }

            /**
             * @param array<string, string> $answers
             */
            public function saveAnswers(int $appointmentId, array $answers, int $now): void
            {
                $this->test->record("answers {$appointmentId}");
                $this->test->savedAnswers = $answers;
            }

            public function find(int $id, bool $forUpdate = false): ?StoredAppointment
            {
                return null;
            }

            /**
             * @param array<string, array{int|string|null, int|string|null}> $changes
             */
            public function update(
                int $id,
                Appointment $appointment,
                StatusChange $change,
                Actor $actor,
                ?string $reason,
                array $changes,
                int $now,
            ): void {
            }

            public function release(int $id): void
            {
            }

            public function occupy(int $id, Claim $claim, int $variantId, int $partySize): void
            {
            }
        };
        $jobs = new class ($test) implements BookingJobs {
            public function __construct(private readonly BookingServiceTest $test)
            {
            }

            public function appointmentBooked(int $appointmentId): void
            {
                $this->test->record("job {$appointmentId}");
            }

            public function appointmentCancelled(int $appointmentId): void
            {
            }

            public function appointmentRescheduled(int $appointmentId): void
            {
            }
        };
        $locker = new class ($test) implements ResourceLocker {
            public function __construct(private readonly BookingServiceTest $test)
            {
            }

            /**
             * @param list<string> $keys
             */
            public function prepare(array $keys, int $from, int $to): void
            {
                $this->test->record('prepare');
            }

            /**
             * @param list<string> $keys
             */
            public function lock(array $keys, int $from, int $to): void
            {
                $this->test->record('lock ' . \implode(',', $keys) . " {$from}-{$to}");
            }
        };
        $transaction = new class ($test) implements TransactionRunner {
            public function __construct(private readonly BookingServiceTest $test)
            {
            }

            public function run(callable $work): mixed
            {
                $this->test->record('begin');
                try {
                    $result = $work();
                } catch (\Throwable $e) {
                    $this->test->record('rollback');
                    throw $e;
                }
                $this->test->record('commit');

                return $result;
            }
        };
        $authorizer = new class ($test) implements Authorizer {
            public function __construct(private readonly BookingServiceTest $test)
            {
            }

            public function allows(string $capability): bool
            {
                return BookingService::CAPABILITY === $capability && $this->test->allowed;
            }
        };
        $fields = new class ($test) implements FieldReader {
            public function __construct(private readonly BookingServiceTest $test)
            {
            }

            /**
             * @return list<Field>
             */
            public function forService(int $serviceId): array
            {
                return $this->test->fields;
            }
        };
        $catalog = new class () implements CatalogApi {
            public function offer(int $variantId): ?Offer
            {
                return 6 === $variantId ? null : new Offer(7, $variantId, 3, 10, 0, null, [], [], []);
            }

            public function location(int $locationId): LocationInfo
            {
                return new LocationInfo($locationId, new \DateTimeZone('Asia/Tehran'), null);
            }
        };

        $customers = new class ($this) implements CustomerApi {
            public function __construct(private readonly BookingServiceTest $test)
            {
            }

            public function canBook(int $customerId): bool
            {
                return $this->test->customerCanBook && 9 === $customerId;
            }
        };

        return new BookingService(
            $catalog,
            $customers,
            $pricing,
            $fields,
            $locker,
            $holds,
            $appointments,
            $jobs,
            $transaction,
            $this->clock,
            $authorizer,
            function (): void {
                $this->record('changed');
            }
        );
    }
}
