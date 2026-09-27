<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Policy;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * One step of a refund ladder: cancelled at least $hours before the start,
 * the customer gets $percent of what they paid back.
 */
final class RefundTier
{
    public function __construct(public readonly int $hours, public readonly int $percent)
    {
        if ($hours < 0 || $percent < 0 || $percent > 100) {
            throw new InvalidValue('invalid_policy', 'A refund tier is hours of 0 or more and 0% to 100%.');
        }
    }
}
