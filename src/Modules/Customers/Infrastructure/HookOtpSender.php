<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Infrastructure;

use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Modules\Customers\Application\OtpSender;

/**
 * Hands a code to whatever the site hooked up (the Notifications module's SMS
 * providers do, and a site's own gateway can too):
 * add_action( "{prefix}/customers/otp", fn ( $phone, $code ) => … ).
 * The code goes to that action and nowhere else: not to a log, not to a reply.
 */
final class HookOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        \do_action(Hooks::name('customers/otp'), $phone, $code);
    }
}
