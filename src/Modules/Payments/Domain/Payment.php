<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;

/**
 * One attempt to pay for an appointment (booking-engine §7). It leaves
 * awaiting_callback once, for succeeded or failed, and never moves again,
 * which is what makes a repeated callback harmless.
 */
final class Payment
{
    /**
     * @param ?int $id null until stored.
     * @param string $authority the gateway's reference for this attempt; unique per gateway.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $appointmentId,
        public readonly string $gateway,
        public readonly Money $amount,
        public readonly PaymentStatus $status,
        public readonly string $authority,
        public readonly ?string $refId = null,
        public readonly ?string $cardMask = null,
    ) {
        if ($appointmentId < 1 || '' === $gateway || '' === $authority) {
            throw new InvalidValue('invalid_payment', 'A payment needs an appointment, a gateway and an authority.');
        }
        if ($amount->isNegative() || $amount->isZero()) {
            throw new InvalidValue('invalid_amount', 'A payment is a positive amount.');
        }
    }

    public function isFinal(): bool
    {
        return PaymentStatus::Succeeded === $this->status || PaymentStatus::Failed === $this->status;
    }

    public function succeeded(?string $refId, ?string $cardMask): self
    {
        return $this->moved(PaymentStatus::Succeeded, $refId, $cardMask);
    }

    public function failed(): self
    {
        return $this->moved(PaymentStatus::Failed, null, null);
    }

    private function moved(PaymentStatus $status, ?string $refId, ?string $cardMask): self
    {
        if ($this->isFinal()) {
            throw new InvalidValue('invalid_transition', 'The payment is already settled.');
        }

        return new self(
            $this->id,
            $this->appointmentId,
            $this->gateway,
            $this->amount,
            $status,
            $this->authority,
            $refId,
            $cardMask
        );
    }
}
