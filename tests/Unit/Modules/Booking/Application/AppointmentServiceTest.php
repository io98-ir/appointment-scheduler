<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Application\Actor;
use Vaqtyar\Modules\Booking\Application\AppointmentRepository;
use Vaqtyar\Modules\Booking\Application\AppointmentService;
use Vaqtyar\Modules\Booking\Application\BookingJobs;
use Vaqtyar\Modules\Booking\Application\BookingService;
use Vaqtyar\Modules\Booking\Application\PolicyReader;
use Vaqtyar\Modules\Booking\Application\ResourceLocker;
use Vaqtyar\Modules\Booking\Application\StoredAppointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\PaymentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\StatusChange;
use Vaqtyar\Modules\Booking\Domain\Appointment\TrackingCode;
use Vaqtyar\Modules\Booking\Domain\Policy\CancellationPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\PolicyEvaluator;
use Vaqtyar\Modules\Booking\Domain\Policy\RefundTier;
use Vaqtyar\Modules\Booking\Domain\Policy\ReschedulePolicy;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceLine;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Modules\Scheduling\Contracts\Claim;
use Vaqtyar\Modules\Scheduling\Contracts\ClaimScope;
use Vaqtyar\Modules\Scheduling\Contracts\SlotClaims;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Shared\Domain\Ulid;
use Vaqtyar\Tests\Fixtures\FixedClock;

/**
 * Cancel, reschedule and no-show on fakes that log each step: the policy
 * decides, staff may override with the capability and a reason, a customer
 * touches only their own, and occupancies change only under the locks.
 * The policy: cancel up to 24 hours before, move up to 12, twice at most.
 */
final class AppointmentServiceTest extends TestCase
{
    /** Two days after the clock. */
    private const START = 1_800_172_800;

    private const NEW_START = 1_800_259_200;

    /** @var list<string> */
    public array $log = [];

    public ?StoredAppointment $stored;

    public ?Claim $claim;

    /** What the read before the locks returns, when it differs. */
    public ?StoredAppointment $staleRead = null;

    /** @var array<string, bool> */
    public array $caps = [BookingService::CAPABILITY => true, AppointmentService::OVERRIDE_CAPABILITY => true];

    /** @var list<array{int, Appointment, StatusChange, Actor, ?string, array<string, mixed>}> */
    public array $updates = [];

    private FixedClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FixedClock('@1800000000');
        $this->stored = self::stored(AppointmentStatus::Confirmed);
        $end = self::NEW_START + 3600;
        $this->claim = new Claim(4, self::NEW_START, $end, self::NEW_START, $end, []);
    }

    public function testACustomerCancelsTheirOwnWithinThePolicy(): void
    {
        $changed = $this->service()->cancel(12, Actor::customer(9), 'Travel');

        self::assertSame(
            [
                'find',
                'begin',
                'lock staff:3 ' . self::START . '-' . (self::START + 3600),
                'find for update',
                'update cancel',
                'release 12',
                'job cancelled 12',
                'commit',
                'changed',
            ],
            $this->log
        );
        self::assertSame(AppointmentStatus::Cancelled, $changed->appointment->status());
        $decision = $changed->decision;
        self::assertSame([true, 100, false], [$decision->allowed, $decision->refundPercent, $changed->overridden]);
        [, , , $actor, $reason] = $this->updates[0];
        self::assertSame([Actor::CUSTOMER, 9, 'Travel'], [$actor->type, $actor->id, $reason]);
    }

    public function testSomeoneElsesAppointmentIsNotFound(): void
    {
        try {
            $this->service()->cancel(12, Actor::customer(10));
            self::fail('No exception.');
        } catch (NotFound $e) {
            self::assertSame('appointment_not_found', $e->errorCode);
        }
        self::assertSame(['find'], $this->log);
    }

    public function testPastTheDeadlineTheCustomerIsRefusedAndNothingChanges(): void
    {
        $this->clock->advance(172_800 - 3600);

        try {
            $this->service()->cancel(12, Actor::customer(9));
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('policy.cancel_window_passed', $e->errorCode);
        }
        self::assertSame([], $this->updates);
        self::assertContains('rollback', $this->log);
        self::assertNotContains('changed', $this->log);
    }

    public function testStaffOverrideThePolicyWithTheCapabilityAndAReason(): void
    {
        $this->clock->advance(172_800 - 3600);

        $changed = $this->service()->cancel(12, Actor::user(2), 'Doctor is ill', true);

        self::assertTrue($changed->overridden);
        self::assertSame('policy.cancel_window_passed', $changed->decision->reasonCode);
        self::assertSame(AppointmentStatus::Cancelled, $changed->appointment->status());
    }

    public function testAnOverrideNeedsTheCapabilityAndAReasonAndNeverComesFromACustomer(): void
    {
        $cases = [
            'customer' => [fn () => $this->service()->cancel(12, Actor::customer(9), 'x', true), Forbidden::class],
            'no reason' => [fn () => $this->service()->cancel(12, Actor::user(2), ' ', true), InvalidValue::class],
        ];
        foreach ($cases as $name => [$call, $expected]) {
            try {
                $call();
                self::fail("No exception: {$name}.");
            } catch (\Throwable $e) {
                self::assertInstanceOf($expected, $e, $name);
            }
        }

        $this->caps[AppointmentService::OVERRIDE_CAPABILITY] = false;
        $this->expectException(Forbidden::class);
        $this->service()->cancel(12, Actor::user(2), 'Ill', true);
    }

    public function testStaffNeedManageBookings(): void
    {
        $this->caps[BookingService::CAPABILITY] = false;

        $this->expectException(Forbidden::class);
        $this->service()->cancel(12, Actor::user(2));
    }

    /**
     * The old and the new time locked together, the appointment's own time
     * freed before the claim, the new time taken after it.
     */
    public function testRescheduleLocksBothTimesFreesItsOwnThenClaims(): void
    {
        $changed = $this->service()->reschedule(12, Actor::customer(9), self::NEW_START, 4);

        $range = self::START . '-' . (self::NEW_START + 7200);
        self::assertSame(
            [
                'find',
                'scope staff 4',
                'prepare res:20,staff:3,staff:4 ' . $range,
                'begin',
                'lock res:20,staff:3,staff:4 ' . $range,
                'find for update',
                'release 12',
                'claim',
                'update reschedule',
                'occupy 12 staff 4',
                'job rescheduled 12',
                'commit',
                'changed',
            ],
            $this->log
        );
        $moved = $changed->appointment;
        self::assertSame([self::NEW_START, 4, '2027-01-18'], [$moved->start, $moved->staffId, $moved->localDate]);
        self::assertSame(
            [
                'start' => [self::START, self::NEW_START],
                'end' => [self::START + 3600, self::NEW_START + 3600],
                'staff_id' => [3, 4],
            ],
            $this->updates[0][5]
        );
    }

    public function testATakenNewTimeIsAConflictAndTheOldTimeIsKept(): void
    {
        $this->claim = null;

        try {
            $this->service()->reschedule(12, Actor::user(2), self::NEW_START);
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('slot_taken', $e->errorCode);
        }
        // The release happened in the transaction that rolled back.
        self::assertSame(['release 12', 'claim', 'rollback'], \array_slice($this->log, -3));
        self::assertSame([], $this->updates);
    }

    /**
     * Moved by someone else between the first read and the locks: its time
     * is not what was locked, so nothing is written.
     */
    public function testAnAppointmentMovedMeanwhileIsAConflict(): void
    {
        $service = $this->service();
        $first = $this->stored;
        self::assertNotNull($first);
        $this->stored = new StoredAppointment(
            12,
            $first->appointment,
            ['staff:8'],
            self::NEW_START,
            self::NEW_START + 3600,
            [],
            1
        );
        $this->staleRead = $first;

        try {
            $service->cancel(12, Actor::user(2));
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('appointment_changed', $e->errorCode);
        }
        self::assertSame([], $this->updates);
        self::assertNotContains('release 12', $this->log);
    }

    public function testTheRescheduleLimitAndWindow(): void
    {
        $this->stored = self::stored(AppointmentStatus::Confirmed, 2);
        try {
            $this->service()->reschedule(12, Actor::customer(9), self::NEW_START);
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('policy.reschedule_limit_reached', $e->errorCode);
        }

        $this->stored = self::stored(AppointmentStatus::Confirmed);
        $this->clock->advance(172_800 - 60);
        try {
            $this->service()->reschedule(12, Actor::customer(9), self::NEW_START);
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('policy.reschedule_window_passed', $e->errorCode);
        }
        self::assertNotContains('release 12', $this->log);
    }

    public function testNoShowOnlyOnceStarted(): void
    {
        try {
            $this->service()->markNoShow(12, 2);
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('not_started', $e->errorCode);
        }

        $this->clock->advance(172_800 + 600);
        $appointment = $this->service()->markNoShow(12, 2);

        self::assertSame(AppointmentStatus::NoShow, $appointment->status());
        self::assertNotContains('release 12', $this->log);
    }

    public function testCompleteOnlyOnceStarted(): void
    {
        try {
            $this->service()->complete(12, 2);
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('not_started', $e->errorCode);
        }

        $this->clock->advance(172_800 + 600);
        $appointment = $this->service()->complete(12, 2);

        self::assertSame(AppointmentStatus::Completed, $appointment->status());
        self::assertSame(['find for update', 'update complete', 'commit'], \array_slice($this->log, -3));
    }

    public function testApproveConfirmsAPendingAppointmentAndKeepsItsTime(): void
    {
        $this->stored = self::stored(AppointmentStatus::PendingApproval);

        $appointment = $this->service()->approve(12, 2);

        self::assertSame(AppointmentStatus::Confirmed, $appointment->status());
        self::assertSame(['begin', 'find for update', 'update approve', 'commit'], $this->log);
    }

    public function testApprovingAConfirmedAppointmentIsAnInvalidTransition(): void
    {
        try {
            $this->service()->approve(12, 2);
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('invalid_transition', $e->errorCode);
        }
        self::assertSame([], $this->updates);
    }

    public function testTheInternalNoteIsSavedWithAHistoryEntry(): void
    {
        $this->service()->saveNote(12, 2, "  Allergic to latex\n");

        self::assertSame(['begin', 'find for update', 'note 12 Allergic to latex', 'commit'], $this->log);
    }

    public function testStaffChangesNeedManageBookingsAndAnExistingAppointment(): void
    {
        $calls = [
            'approve' => fn () => $this->service()->approve(13, 2),
            'complete' => fn () => $this->service()->complete(13, 2),
            'note' => fn () => $this->service()->saveNote(13, 2, 'x'),
        ];
        foreach ($calls as $name => $call) {
            try {
                $call();
                self::fail("No exception: {$name}.");
            } catch (NotFound $e) {
                self::assertSame('appointment_not_found', $e->errorCode, $name);
            }
        }

        $this->caps[BookingService::CAPABILITY] = false;
        $this->expectException(Forbidden::class);
        $this->service()->saveNote(12, 2, 'x');
    }

    public function record(string $entry): void
    {
        $this->log[] = $entry;
    }

    private static function stored(AppointmentStatus $status, int $rescheduled = 0): StoredAppointment
    {
        $appointment = Appointment::restore(
            Ulid::fromParts(new \DateTimeImmutable('@1799000000'), \str_repeat("\1", 10)),
            TrackingCode::fromString('AB12CD34'),
            9,
            1,
            7,
            5,
            3,
            self::START,
            self::START + 3600,
            '2027-01-17',
            'Asia/Tehran',
            1,
            PriceQuote::empty()->with(new PriceLine(PriceLine::BASE, Money::ofRial(1_000_000))),
            '',
            PaymentStatus::Unpaid,
            $status
        );

        return new StoredAppointment(12, $appointment, ['staff:3'], self::START, self::START + 3600, [], $rescheduled);
    }

    private function service(): AppointmentService
    {
        $test = $this;
        $appointments = new class ($test) implements AppointmentRepository {
            public function __construct(private readonly AppointmentServiceTest $test)
            {
            }

            public function add(
                Appointment $appointment,
                StatusChange $change,
                string $source,
                ?int $userId,
                int $now,
            ): int {
                throw new \LogicException('Not used.');
            }

            /**
             * @param array<string, string> $answers
             */
            public function saveAnswers(int $appointmentId, array $answers, int $now): void
            {
                throw new \LogicException('Not used.');
            }

            public function find(int $id, bool $forUpdate = false): ?StoredAppointment
            {
                $this->test->record($forUpdate ? 'find for update' : 'find');
                if (12 !== $id) {
                    return null;
                }

                return $forUpdate ? $this->test->stored : ($this->test->staleRead ?? $this->test->stored);
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
                $this->test->record("update {$change->action}");
                $this->test->updates[] = [$id, $appointment, $change, $actor, $reason, $changes];
            }

            /**
             * @return list<int>
             */
            public function pendingPaymentBefore(int $cutoff, int $limit): array
            {
                return [];
            }

            public function release(int $id): void
            {
                $this->test->record("release {$id}");
            }

            public function occupy(int $id, Claim $claim, int $variantId, int $partySize): void
            {
                $this->test->record("occupy {$id} staff {$claim->staffId}");
            }

            public function saveNote(int $id, Appointment $appointment, string $note, Actor $actor, int $now): void
            {
                $this->test->record("note {$id} {$note}");
            }
        };
        $policies = new class () implements PolicyReader {
            public function forService(int $serviceId): PolicyEvaluator
            {
                return new PolicyEvaluator(
                    new CancellationPolicy(24, [new RefundTier(48, 100), new RefundTier(24, 50)]),
                    new ReschedulePolicy(12, 2)
                );
            }
        };
        $slots = new class ($test) implements SlotClaims {
            public function __construct(private readonly AppointmentServiceTest $test)
            {
            }

            public function scope(AvailabilityQuery $query, int $start): ClaimScope
            {
                $this->test->record("scope staff {$query->staffId}");

                return new ClaimScope([4], [20], $start, $start + 7200);
            }

            public function claim(AvailabilityQuery $query, int $start): ?Claim
            {
                $this->test->record('claim');

                return $this->test->claim;
            }
        };
        $locker = new class ($test) implements ResourceLocker {
            public function __construct(private readonly AppointmentServiceTest $test)
            {
            }

            /**
             * @param list<string> $keys
             */
            public function prepare(array $keys, int $from, int $to): void
            {
                \sort($keys);
                $this->test->record('prepare ' . \implode(',', $keys) . " {$from}-{$to}");
            }

            /**
             * @param list<string> $keys
             */
            public function lock(array $keys, int $from, int $to): void
            {
                \sort($keys);
                $this->test->record('lock ' . \implode(',', $keys) . " {$from}-{$to}");
            }
        };
        $jobs = new class ($test) implements BookingJobs {
            public function __construct(private readonly AppointmentServiceTest $test)
            {
            }

            public function appointmentBooked(int $appointmentId): void
            {
            }

            public function appointmentCancelled(int $appointmentId): void
            {
                $this->test->record("job cancelled {$appointmentId}");
            }

            public function appointmentRescheduled(int $appointmentId): void
            {
                $this->test->record("job rescheduled {$appointmentId}");
            }
        };
        $transaction = new class ($test) implements TransactionRunner {
            public function __construct(private readonly AppointmentServiceTest $test)
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
            public function __construct(private readonly AppointmentServiceTest $test)
            {
            }

            public function allows(string $capability): bool
            {
                return $this->test->caps[$capability] ?? false;
            }
        };

        return new AppointmentService(
            $appointments,
            $policies,
            $slots,
            $locker,
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
