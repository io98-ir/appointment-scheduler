<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Application;

/**
 * Delivers a one-time code to a phone (T4.3). The SMS providers of T5.5
 * implement it; until then a site hooks its own gateway.
 */
interface OtpSender
{
    public function send(string $phone, string $code): void;
}
