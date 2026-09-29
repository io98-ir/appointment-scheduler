<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

/**
 * A gateway's verdict on a payment.
 */
final class Verification
{
    public function __construct(
        public readonly bool $paid,
        public readonly ?string $refId = null,
        public readonly ?string $cardMask = null,
    ) {
    }
}
