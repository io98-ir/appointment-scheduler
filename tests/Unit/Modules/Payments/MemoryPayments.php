<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use Vaqtyar\Modules\Payments\Application\PaymentRepository;
use Vaqtyar\Modules\Payments\Domain\Payment;
use Vaqtyar\Modules\Payments\Domain\PaymentStatus;

/**
 * Payments kept in memory, for the tests of what sits on top of them.
 */
final class MemoryPayments implements PaymentRepository
{
    /** @var array<int, Payment> by id */
    public array $payments = [];

    public function add(Payment $payment, int $now): Payment
    {
        $id = \count($this->payments) + 1;
        $added = new Payment(
            $id,
            $payment->appointmentId,
            $payment->gateway,
            $payment->amount,
            $payment->status,
            $payment->authority
        );
        $this->payments[$id] = $added;

        return $added;
    }

    public function find(string $gateway, string $authority, bool $forUpdate = false): ?Payment
    {
        foreach ($this->payments as $payment) {
            if ($payment->gateway === $gateway && $payment->authority === $authority) {
                return $payment;
            }
        }

        return null;
    }

    public function findById(int $id, bool $forUpdate = false): ?Payment
    {
        return $this->payments[$id] ?? null;
    }

    /**
     * @return list<Payment>
     */
    public function forAppointment(int $appointmentId): array
    {
        return \array_values(\array_filter(
            $this->payments,
            static fn (Payment $payment): bool => $payment->appointmentId === $appointmentId
        ));
    }

    public function paidTotal(int $appointmentId): int
    {
        return \array_sum(\array_map(
            static fn (Payment $payment): int => PaymentStatus::Succeeded === $payment->status
                ? $payment->amount->amount
                : 0,
            $this->forAppointment($appointmentId)
        ));
    }

    /**
     * @return list<Payment>
     */
    public function awaitingBefore(int $cutoff, int $limit): array
    {
        return [];
    }

    public function settle(Payment $payment, int $now): bool
    {
        return false;
    }
}
