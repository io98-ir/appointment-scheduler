<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Application\Actor;
use Vaqtyar\Modules\Booking\Application\AppointmentPayments;
use Vaqtyar\Modules\Booking\Application\AppointmentRepository;
use Vaqtyar\Modules\Booking\Application\BookingJobs;
use Vaqtyar\Modules\Booking\Application\StoredAppointment;
use Vaqtyar\Modules\Booking\Application\TermsReader;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\PaymentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\StatusChange;
use Vaqtyar\Modules\Booking\Domain\Appointment\TrackingCode;
use Vaqtyar\Modules\Booking\Domain\Policy\ApprovalPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\BookingTerms;
use Vaqtyar\Modules\Booking\Domain\Policy\BookingWindowPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\DepositPolicy;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceLine;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Modules\Payments\Contracts\PaymentsApi;
use Vaqtyar\Modules\Payments\Contracts\PaymentTotals;
use Vaqtyar\Modules\Scheduling\Contracts\Claim;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Shared\Domain\Ulid;

/**
 * An appointment taking in the money: a payment that confirms a booking waiting for it, a deposit,
 * the rest, a refund; and what is left alone.
 */
final class AppointmentPaymentsTest extends TestCase
{
    public const NOW = 1_800_000_000;

    private const PRICE = 1_000_000;

    private ?StoredAppointment $stored = null;

    /** @var list<string> */
    public array $log = [];

    public int $paid = 0;

    public int $refunded = 0;

    public bool $approval = false;

    /** @var list<array<string, array{int|string|null, int|string|null}>> */
    public array $changes = [];

    public function testAFullPaymentConfirmsABookingThatWaitedForIt(): void
    {
        $this->stored = self::stored(AppointmentStatus::PendingPayment);
        $this->paid = self::PRICE;

        self::assertTrue($this->service()->received(12));

        self::assertSame(AppointmentStatus::Confirmed, $this->stored->appointment->status());
        self::assertSame(PaymentStatus::Paid, $this->stored->appointment->paymentStatus());
        self::assertSame(['find 12 for update', 'update paid by system', 'job 12'], $this->log);
    }

    public function testADepositConfirmsItTooAndLeavesTheRestToPayLater(): void
    {
        $this->stored = self::stored(AppointmentStatus::PendingPayment);
        $this->paid = 300_000;

        self::assertTrue($this->service()->received(12));

        self::assertSame(AppointmentStatus::Confirmed, $this->stored->appointment->status());
        self::assertSame(PaymentStatus::DepositPaid, $this->stored->appointment->paymentStatus());
    }

    public function testAServiceThatNeedsApprovalHandsAPaidBookingToStaff(): void
    {
        $this->stored = self::stored(AppointmentStatus::PendingPayment);
        $this->paid = self::PRICE;
        $this->approval = true;

        self::assertTrue($this->service()->received(12));

        self::assertSame(AppointmentStatus::PendingApproval, $this->stored->appointment->status());
        self::assertSame(PaymentStatus::Paid, $this->stored->appointment->paymentStatus());
    }

    public function testTheRestPaidLaterTakesADepositToPaid(): void
    {
        $this->stored = self::stored(AppointmentStatus::Confirmed, PaymentStatus::DepositPaid);
        $this->paid = self::PRICE;

        self::assertTrue($this->service()->received(12));

        self::assertSame(PaymentStatus::Paid, $this->stored->appointment->paymentStatus());
        self::assertSame(AppointmentStatus::Confirmed, $this->stored->appointment->status());
        self::assertSame(['find 12 for update', 'update payment by system'], $this->log, 'No second announcement.');
        self::assertSame([['payment_status' => ['deposit_paid', 'paid']]], $this->changes);
    }

    public function testMoneyRecordedForAnAppointmentPaidAtThePlaceMarksItPaid(): void
    {
        $this->stored = self::stored(AppointmentStatus::Completed);
        $this->paid = self::PRICE;

        self::assertTrue($this->service()->received(12));

        self::assertSame(PaymentStatus::Paid, $this->stored->appointment->paymentStatus());
    }

    public function testAPaymentForACancelledOrExpiredAppointmentIsNotAppliedAndStaffAreTold(): void
    {
        foreach ([AppointmentStatus::Cancelled, AppointmentStatus::Expired] as $status) {
            $this->log = [];
            $this->stored = self::stored($status);
            $this->paid = self::PRICE;

            self::assertFalse($this->service()->received(12));
            self::assertSame(['find 12 for update'], $this->log);
            self::assertSame(PaymentStatus::Unpaid, $this->stored->appointment->paymentStatus());
        }
        $this->stored = null;
        self::assertFalse($this->service()->received(12));
    }

    public function testNoMoneyOnRecordAppliesNothing(): void
    {
        $this->stored = self::stored(AppointmentStatus::PendingPayment);
        $this->paid = 0;

        self::assertFalse($this->service()->received(12));

        self::assertSame(AppointmentStatus::PendingPayment, $this->stored->appointment->status());
        self::assertSame(['find 12 for update'], $this->log);
    }

    public function testARefundFollowsInAnyStatusEvenAfterACancellation(): void
    {
        $this->stored = self::stored(AppointmentStatus::Cancelled, PaymentStatus::Paid);
        $this->paid = self::PRICE;
        $this->refunded = 400_000;

        $this->service()->changed(12);
        self::assertSame(PaymentStatus::PartiallyRefunded, $this->stored->appointment->paymentStatus());

        $this->refunded = self::PRICE;
        $this->service()->changed(12);
        self::assertSame(PaymentStatus::Refunded, $this->stored->appointment->paymentStatus());
        self::assertSame(AppointmentStatus::Cancelled, $this->stored->appointment->status());
    }

    public function testNothingIsWrittenWhenThePaymentStatusAlreadyAgrees(): void
    {
        $this->stored = self::stored(AppointmentStatus::Confirmed, PaymentStatus::Paid);
        $this->paid = self::PRICE;

        $this->service()->changed(12);

        self::assertSame(['find 12 for update'], $this->log);
        $this->service()->changed(99);
        self::assertSame(['find 99 for update'], $this->log, 'An appointment that is gone is left alone.');
    }

    private static function stored(
        AppointmentStatus $status,
        PaymentStatus $paymentStatus = PaymentStatus::Unpaid
    ): StoredAppointment {
        $appointment = Appointment::restore(
            Ulid::fromParts(new \DateTimeImmutable('@1799000000'), \str_repeat("\1", 10)),
            TrackingCode::fromString('AB12CD34'),
            9,
            1,
            7,
            5,
            3,
            self::NOW + 86_400,
            self::NOW + 90_000,
            '2027-01-17',
            'Asia/Tehran',
            1,
            PriceQuote::empty()->with(new PriceLine(PriceLine::BASE, Money::ofRial(self::PRICE))),
            '',
            $paymentStatus,
            $status
        );

        return new StoredAppointment(12, $appointment, ['staff:3'], self::NOW + 86_400, self::NOW + 90_000, [], 0);
    }

    public function record(string $entry): void
    {
        $this->log[] = $entry;
    }

    public function current(?int $id): ?StoredAppointment
    {
        return 12 === $id ? $this->stored : null;
    }

    private function service(): AppointmentPayments
    {
        $this->log = [];
        $this->changes = [];
        $test = $this;

        return new AppointmentPayments(
            new class ($test) implements AppointmentRepository {
                public function __construct(private readonly AppointmentPaymentsTest $test)
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
                    $this->test->record("find {$id}" . ($forUpdate ? ' for update' : ''));

                    return $this->test->current($id);
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
                    $this->test->record("update {$change->action} by {$actor->type}");
                    if ([] !== $changes) {
                        $this->test->changes[] = $changes;
                    }
                }

                /**
                 * @return list<int>
                 */
                public function pendingPaymentBefore(int $cutoff, int $limit): array
                {
                    throw new \LogicException('Not used.');
                }

                public function release(int $id): void
                {
                    throw new \LogicException('Not used.');
                }

                public function occupy(int $id, Claim $claim, int $variantId, int $partySize): void
                {
                    throw new \LogicException('Not used.');
                }

                public function saveNote(int $id, Appointment $appointment, string $note, Actor $actor, int $now): void
                {
                    throw new \LogicException('Not used.');
                }
            },
            new class ($test) implements PaymentsApi {
                public function __construct(private readonly AppointmentPaymentsTest $test)
                {
                }

                public function onlineAvailable(): bool
                {
                    return true;
                }

                public function startOnline(int $appointmentId, Money $amount, string $returnUrl): string
                {
                    throw new \LogicException('Not used.');
                }

                public function totals(int $appointmentId): PaymentTotals
                {
                    return new PaymentTotals($this->test->paid, $this->test->refunded);
                }
            },
            new class ($test) implements TermsReader {
                public function __construct(private readonly AppointmentPaymentsTest $test)
                {
                }

                public function termsFor(int $serviceId): BookingTerms
                {
                    return new BookingTerms(
                        DepositPolicy::lenient(),
                        new ApprovalPolicy($this->test->approval),
                        BookingWindowPolicy::lenient()
                    );
                }
            },
            new class ($test) implements BookingJobs {
                public function __construct(private readonly AppointmentPaymentsTest $test)
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
            },
            new class implements TransactionRunner {
                public function run(callable $work): mixed
                {
                    return $work();
                }
            },
            new class implements Clock {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('@' . AppointmentPaymentsTest::NOW);
                }
            }
        );
    }
}
