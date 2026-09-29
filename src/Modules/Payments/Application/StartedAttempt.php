<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

/**
 * What a gateway answers to start: its reference, and where to send the
 * customer (null for a gateway with no page, such as offline).
 */
final class StartedAttempt
{
    public function __construct(public readonly string $authority, public readonly ?string $redirectUrl)
    {
    }
}
