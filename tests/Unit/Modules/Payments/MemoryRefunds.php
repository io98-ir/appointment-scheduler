<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use Vaqtyar\Modules\Payments\Application\RefundRepository;
use Vaqtyar\Shared\Domain\Money;

/**
 * Refunds kept in memory.
 */
final class MemoryRefunds implements RefundRepository
{
    /** @var array<int, array{payment: int, appointment: int, amount: int}> by id */
    public array $refunds = [];

    public function add(int $paymentId, int $appointmentId, Money $amount, string $reason, ?int $userId, int $now): int
    {
        $id = \count($this->refunds) + 1;
        $this->refunds[$id] = ['payment' => $paymentId, 'appointment' => $appointmentId, 'amount' => $amount->amount];

        return $id;
    }

    public function refundedTotal(int $paymentId): int
    {
        return \array_sum(\array_column(
            \array_filter($this->refunds, static fn (array $refund): bool => $refund['payment'] === $paymentId),
            'amount'
        ));
    }

    public function refundedTotalOfAppointment(int $appointmentId): int
    {
        return \array_sum(\array_column(
            \array_filter($this->refunds, static fn (array $refund): bool => $refund['appointment'] === $appointmentId),
            'amount'
        ));
    }
}
