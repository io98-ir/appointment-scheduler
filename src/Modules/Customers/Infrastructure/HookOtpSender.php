<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Infrastructure;

use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Modules\Customers\Application\OtpSender;

/**
 * Hands a code to whatever the site hooked up, until the SMS providers of
 * T5.5 exist: add_action( "{prefix}/customers/otp", fn ( $phone, $code ) => … ).
 * The code goes to that action and nowhere else: not to a log, not to a reply.
 */
final class HookOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        \do_action(Hooks::name('customers/otp'), $phone, $code);
    }
}
