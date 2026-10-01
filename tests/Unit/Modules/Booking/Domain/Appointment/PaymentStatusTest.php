<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Domain\Appointment;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\PaymentStatus as P;
use Vaqtyar\Modules\Booking\Domain\Appointment\TrackingCode;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceLine;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Ulid;

/**
 * What the money paid says about an appointment, and how an appointment takes it in.
 */
final class PaymentStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int, P}> price, paid, refunded, resulting status.
     */
    public static function money(): iterable
    {
        yield 'nothing paid' => [1_000, 0, 0, P::Unpaid];
        yield 'a refund of nothing is still unpaid' => [1_000, 0, 500, P::Unpaid];
        yield 'a deposit' => [1_000, 300, 0, P::DepositPaid];
        yield 'paid in full' => [1_000, 1_000, 0, P::Paid];
        yield 'paid more than the price' => [1_000, 1_200, 0, P::Paid];
        yield 'part of it refunded' => [1_000, 1_000, 400, P::PartiallyRefunded];
        yield 'all of it refunded' => [1_000, 1_000, 1_000, P::Refunded];
        yield 'a deposit refunded in full' => [1_000, 300, 300, P::Refunded];
        yield 'a free appointment paid for nothing stays unpaid' => [0, 0, 0, P::Unpaid];
    }

    /**
     * @dataProvider money
     */
    public function testResolve(int $total, int $paid, int $refunded, P $expected): void
    {
        self::assertSame($expected, P::resolve($total, $paid, $refunded));
    }

    public function testPayingTakesAWaitingBookingToConfirmedWithTheStatusItIsGiven(): void
    {
        $appointment = self::appointment(AppointmentStatus::PendingPayment);

        $change = $appointment->paid(false, P::DepositPaid);

        self::assertSame(AppointmentStatus::Confirmed, $appointment->status());
        self::assertSame(P::DepositPaid, $appointment->paymentStatus());
        self::assertSame(['paid', AppointmentStatus::PendingPayment, AppointmentStatus::Confirmed], [
            $change->action,
            $change->from,
            $change->to,
        ]);
    }

    public function testPayingStillDefaultsToPaidInFull(): void
    {
        $appointment = self::appointment(AppointmentStatus::PendingPayment);

        $appointment->paid(true);

        self::assertSame(AppointmentStatus::PendingApproval, $appointment->status());
        self::assertSame(P::Paid, $appointment->paymentStatus());
    }

    /**
     * @return iterable<string, array{AppointmentStatus}>
     */
    public static function statuses(): iterable
    {
        foreach (AppointmentStatus::cases() as $status) {
            if (AppointmentStatus::Completed !== $status && AppointmentStatus::NoShow !== $status) {
                yield $status->value => [$status];
            }
        }
    }

    /**
     * @dataProvider statuses
     */
    public function testTheMoneyChangesTheStatusOfPaymentInAnyStatusAndNothingElse(AppointmentStatus $status): void
    {
        $appointment = self::appointment($status);

        $change = $appointment->recordPayment(P::Refunded);

        self::assertSame(P::Refunded, $appointment->paymentStatus());
        self::assertSame($status, $appointment->status());
        self::assertSame(['payment', $status, $status], [$change->action, $change->from, $change->to]);
    }

    private static function appointment(AppointmentStatus $status): Appointment
    {
        $appointment = Appointment::book(
            Ulid::fromParts(new \DateTimeImmutable('2026-10-01'), \str_repeat('a', 10)),
            TrackingCode::fromString('AB12CD34'),
            1,
            1,
            1,
            1,
            1,
            1_800_000_000,
            1_800_003_600,
            '2027-01-15',
            'Asia/Tehran',
            1,
            PriceQuote::fromArray(['lines' => [
                ['code' => PriceLine::BASE, 'amount' => Money::ofRial(1_000)->toArray(), 'ref' => null, 'qty' => 1],
            ]]),
            '',
            AppointmentStatus::Confirmed
        );
        if (AppointmentStatus::Confirmed === $status) {
            return $appointment;
        }

        return Appointment::restore(
            Ulid::fromParts(new \DateTimeImmutable('2026-10-01'), \str_repeat('a', 10)),
            TrackingCode::fromString('AB12CD34'),
            1,
            1,
            1,
            1,
            1,
            1_800_000_000,
            1_800_003_600,
            '2027-01-15',
            'Asia/Tehran',
            1,
            $appointment->quote,
            '',
            P::Unpaid,
            $status
        );
    }
}
