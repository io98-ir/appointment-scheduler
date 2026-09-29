<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Application;

/**
 * A code as kept: its keyed hash, and how many guesses it has had.
 */
final class StoredOtp
{
    public function __construct(
        public readonly int $id,
        public readonly string $hash,
        public readonly int $attempts,
    ) {
    }
}
