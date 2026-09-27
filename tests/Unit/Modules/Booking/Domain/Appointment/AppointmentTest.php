<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Domain\Appointment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus as S;
use Vaqtyar\Modules\Booking\Domain\Appointment\PaymentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\StatusChange;
use Vaqtyar\Modules\Booking\Domain\Appointment\TrackingCode;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceLine;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Ulid;

/**
 * The state machine of booking-engine §4: every transition it allows, and
 * every other one refused.
 */
final class AppointmentTest extends TestCase
{
    private const START = 1_800_003_600;

    public function testBookingRecordsTheCreationAndStartsUnpaid(): void
    {
        $appointment = self::book(S::Confirmed);

        self::assertSame(S::Confirmed, $appointment->status());
        self::assertSame(PaymentStatus::Unpaid, $appointment->paymentStatus);
        self::assertSame('2027-01-15', $appointment->localDate);
        self::assertSame(1_200_000, $appointment->quote->total()->amount);
        $created = $appointment->created();
        self::assertSame(['created', null, S::Confirmed], [$created->action, $created->from, $created->to]);
    }

    public function testABookingStartsOnlyConfirmedOrPending(): void
    {
        foreach ([S::Completed, S::NoShow, S::Cancelled, S::Expired] as $status) {
            try {
                self::book($status);
                self::fail("Booked as {$status->value}.");
            } catch (InvalidValue $e) {
                self::assertSame('invalid_status', $e->errorCode);
            }
        }
    }

    public function testABookingMustEndAfterItStarts(): void
    {
        $this->expectException(InvalidValue::class);
        self::book(S::Confirmed, self::START);
    }

    /**
     * @return iterable<string, array{S, string, S}>
     */
    public static function allowed(): iterable
    {
        yield 'approve' => [S::PendingApproval, 'approve', S::Confirmed];
        yield 'paid' => [S::PendingPayment, 'paid', S::Confirmed];
        yield 'paid needing approval' => [S::PendingPayment, 'paidAwaitingApproval', S::PendingApproval];
        yield 'expire' => [S::PendingPayment, 'expire', S::Expired];
        yield 'complete' => [S::Confirmed, 'complete', S::Completed];
        yield 'no show' => [S::Confirmed, 'noShow', S::NoShow];
        yield 'cancel confirmed' => [S::Confirmed, 'cancel', S::Cancelled];
        yield 'cancel awaiting approval' => [S::PendingApproval, 'cancel', S::Cancelled];
        yield 'cancel awaiting payment' => [S::PendingPayment, 'cancel', S::Cancelled];
    }

    /** @dataProvider allowed */
    public function testAllowedTransition(S $from, string $method, S $to): void
    {
        $appointment = self::book($from);

        $change = self::apply($appointment, $method);

        self::assertSame([$from, $to], [$change->from, $change->to]);
        self::assertSame($to, $appointment->status());
    }

    /**
     * Every pair the machine does not list, starting from any status an
     * appointment can reach.
     */
    public function testEveryOtherTransitionIsRefusedAndChangesNothing(): void
    {
        $allowed = [];
        foreach (self::allowed() as [$from, $method]) {
            $allowed[$from->value . ' ' . $method] = true;
        }
        $methods = ['approve', 'paid', 'paidAwaitingApproval', 'expire', 'complete', 'noShow', 'cancel'];
        $refused = 0;
        foreach (S::cases() as $from) {
            foreach ($methods as $method) {
                if (isset($allowed[$from->value . ' ' . $method])) {
                    continue;
                }
                $appointment = self::reach($from);
                try {
                    self::apply($appointment, $method);
                    self::fail("{$method} from {$from->value} was allowed.");
                } catch (Conflict $e) {
                    self::assertSame('invalid_transition', $e->errorCode);
                }
                self::assertSame($from, $appointment->status());
                ++$refused;
            }
        }
        self::assertSame(7 * 7 - 9, $refused);
    }

    public function testCancelKeepsWhenAndWhy(): void
    {
        $appointment = self::book(S::Confirmed);

        $appointment->cancel(self::START - 3600, 'Sick');

        self::assertSame([self::START - 3600, 'Sick'], [$appointment->cancelledAt(), $appointment->cancelReason()]);
    }

    public function testRescheduleMovesAConfirmedAppointmentAndKeepsTheRest(): void
    {
        $appointment = self::book(S::Confirmed);

        [$moved, $change] = $appointment->reschedule(self::START + 86_400, self::START + 90_000, 4, '2027-01-16');

        self::assertSame(
            [self::START + 86_400, self::START + 90_000, 4, '2027-01-16', S::Confirmed],
            [$moved->start, $moved->end, $moved->staffId, $moved->localDate, $moved->status()]
        );
        self::assertSame($appointment->uuid, $moved->uuid);
        self::assertSame($appointment->quote, $moved->quote);
        self::assertSame(['reschedule', S::Confirmed, S::Confirmed], [$change->action, $change->from, $change->to]);
        self::assertSame(self::START, $appointment->start);
    }

    public function testOnlyAConfirmedAppointmentIsRescheduled(): void
    {
        foreach ([S::PendingPayment, S::Cancelled, S::Completed] as $status) {
            try {
                self::reach($status)->reschedule(self::START + 86_400, self::START + 90_000, 3, '2027-01-16');
                self::fail("Rescheduled when {$status->value}.");
            } catch (Conflict $e) {
                self::assertSame('invalid_transition', $e->errorCode);
            }
        }
    }

    public function testRestoreKeepsTheStoredState(): void
    {
        $booked = self::book(S::Confirmed);

        $restored = Appointment::restore(
            $booked->uuid,
            $booked->code,
            9,
            1,
            7,
            5,
            3,
            self::START,
            self::START + 3600,
            '2027-01-15',
            'Asia/Tehran',
            1,
            $booked->quote,
            '',
            PaymentStatus::Paid,
            S::Cancelled,
            self::START - 60,
            'Sick'
        );

        self::assertSame(
            [S::Cancelled, PaymentStatus::Paid, self::START - 60, 'Sick'],
            [$restored->status(), $restored->paymentStatus, $restored->cancelledAt(), $restored->cancelReason()]
        );
    }

    public function testTrackingCodesAreEightUnambiguousCharacters(): void
    {
        for ($i = 0; $i < 50; ++$i) {
            self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{8}$/D', TrackingCode::generate()->value);
        }
        self::assertSame('AB12CD34', TrackingCode::fromString('ab12cd34')->value);
        $this->expectException(InvalidValue::class);
        TrackingCode::fromString('AB12CD3O');
    }

    private static function apply(Appointment $appointment, string $method): StatusChange
    {
        return match ($method) {
            'approve' => $appointment->approve(),
            'paid' => $appointment->paid(false),
            'paidAwaitingApproval' => $appointment->paid(true),
            'expire' => $appointment->expire(),
            'complete' => $appointment->complete(),
            'noShow' => $appointment->markNoShow(),
            'cancel' => $appointment->cancel(self::START - 60, null),
            default => throw new \LogicException($method),
        };
    }

    private static function reach(S $status): Appointment
    {
        return match ($status) {
            S::PendingApproval, S::PendingPayment, S::Confirmed => self::book($status),
            S::Completed => self::then(self::book(S::Confirmed), 'complete'),
            S::NoShow => self::then(self::book(S::Confirmed), 'noShow'),
            S::Cancelled => self::then(self::book(S::Confirmed), 'cancel'),
            S::Expired => self::then(self::book(S::PendingPayment), 'expire'),
        };
    }

    private static function then(Appointment $appointment, string $method): Appointment
    {
        self::apply($appointment, $method);

        return $appointment;
    }

    private static function book(S $status, int $end = self::START + 3600): Appointment
    {
        return Appointment::book(
            Ulid::fromParts(new \DateTimeImmutable('@1800000000'), \str_repeat("\0", 10)),
            TrackingCode::fromString('AB12CD34'),
            9,
            1,
            7,
            5,
            3,
            self::START,
            $end,
            '2027-01-15',
            'Asia/Tehran',
            1,
            PriceQuote::empty()->with(new PriceLine(PriceLine::BASE, Money::ofRial(1_200_000))),
            '',
            $status
        );
    }
}
