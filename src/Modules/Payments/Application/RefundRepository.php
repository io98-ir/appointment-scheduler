<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

use Vaqtyar\Shared\Domain\Money;

interface RefundRepository
{
    /**
     * @return int the refund's id.
     */
    public function add(int $paymentId, int $appointmentId, Money $amount, string $reason, ?int $userId, int $now): int;

    /**
     * What has been refunded from a payment so far, in rials.
     */
    public function refundedTotal(int $paymentId): int;
}
