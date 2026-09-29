<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

use Vaqtyar\Modules\Payments\Domain\Payment;

/**
 * A payment that was opened, and where to send the customer to pay it.
 */
final class StartedPayment
{
    public function __construct(public readonly Payment $payment, public readonly ?string $redirectUrl)
    {
    }
}
