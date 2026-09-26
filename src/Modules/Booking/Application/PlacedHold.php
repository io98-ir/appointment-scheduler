<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Hold;

/**
 * A new hold and the only copy of its token, for the client.
 */
final class PlacedHold
{
    public function __construct(
        public readonly int $id,
        public readonly string $token,
        public readonly Hold $hold,
    ) {
    }
}
