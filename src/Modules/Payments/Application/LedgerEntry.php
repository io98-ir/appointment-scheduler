<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

use Vaqtyar\Modules\Payments\Domain\Payment;

/**
 * One payment of an appointment and what has been refunded against it so far, in rials.
 */
final class LedgerEntry
{
    public function __construct(public readonly Payment $payment, public readonly int $refunded)
    {
    }
}
