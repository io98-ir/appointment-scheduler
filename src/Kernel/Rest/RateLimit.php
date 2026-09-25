<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Rest;

use Vaqtyar\Kernel\KernelException;

/**
 * At most $limit attempts per fixed window of $windowSeconds.
 */
final class RateLimit
{
    public function __construct(
        public readonly int $limit,
        public readonly int $windowSeconds,
    ) {
        if ($limit < 1 || $windowSeconds < 1) {
            throw KernelException::invalidRateLimit($limit, $windowSeconds);
        }
    }
}
