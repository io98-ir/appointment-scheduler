<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

use Vaqtyar\Modules\Payments\Domain\PaymentStatus;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\TransactionRunner;

/**
 * Records a refund staff made by hand, outside the gateway (booking-engine
 * §7): money is returned in the gateway's panel or by card transfer, and
 * this is the ledger of it. Refunds through a gateway's API are not built;
 * no gateway of the first release offers one worth the risk. The payment row
 * is locked while the total is checked, so two staff cannot together refund
 * more than was paid.
 */
final class RefundService
{
    private const CAPABILITY = 'manage_bookings';
    public const METHOD_MANUAL = 'manual';
    public const STATUS_RECORDED = 'recorded';

    /**
     * @param \Closure(int, int): void $onRefunded Called after the commit with the appointment and refund ids.
     */
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly RefundRepository $refunds,
        private readonly TransactionRunner $transaction,
        private readonly Clock $clock,
        private readonly Authorizer $authorizer,
        private readonly \Closure $onRefunded,
    ) {
    }

    /**
     * @return int the refund's id.
     * @throws Forbidden without the booking capability.
     * @throws NotFound payment_not_found
     * @throws InvalidValue invalid_amount for an amount that is not positive.
     * @throws Conflict payment_not_paid, or refund_exceeds_payment.
     */
    public function record(int $paymentId, Money $amount, string $reason, int $userId): int
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
        if ($amount->isNegative() || $amount->isZero()) {
            throw new InvalidValue('invalid_amount', 'A refund is a positive amount.');
        }
        $appointmentId = 0;
        $id = $this->transaction->run(function () use ($paymentId, $amount, $reason, $userId, &$appointmentId): int {
            $payment = $this->payments->findById($paymentId, true)
                ?? throw new NotFound('payment_not_found', 'There is no such payment.');
            if (PaymentStatus::Succeeded !== $payment->status) {
                throw new Conflict('payment_not_paid', 'Only a payment that went through can be refunded.');
            }
            if ($this->refunds->refundedTotal($paymentId) + $amount->amount > $payment->amount->amount) {
                throw new Conflict('refund_exceeds_payment', 'The refunds would be more than the payment.');
            }
            $appointmentId = $payment->appointmentId;

            return $this->refunds->add(
                $paymentId,
                $payment->appointmentId,
                $amount,
                $reason,
                $userId,
                $this->clock->now()->getTimestamp()
            );
        });
        ($this->onRefunded)($appointmentId, $id);

        return $id;
    }
}
