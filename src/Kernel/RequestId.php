<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * Identifies the current request in error responses and log lines, so a
 * support request that quotes it finds the matching log entry (architecture §13).
 * One per request: a container singleton.
 */
final class RequestId
{
    private readonly string $value;

    public function __construct()
    {
        $this->value = \bin2hex(\random_bytes(8));
    }

    public function value(): string
    {
        return $this->value;
    }
}
