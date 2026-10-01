<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Contracts;

/**
 * What has been paid for an appointment and what has been given back, in rials: the sum of the
 * payments that went through, and the sum of the refunds recorded against them.
 */
final class PaymentTotals
{
    public function __construct(public readonly int $paid, public readonly int $refunded)
    {
    }
}
