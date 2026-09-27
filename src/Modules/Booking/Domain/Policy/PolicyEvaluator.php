<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Policy;

use Vaqtyar\Shared\Domain\Money;

/**
 * The policies of one service, the service's own over the global ones
 * (booking-engine §6), and what they say about a change.
 */
final class PolicyEvaluator
{
    public function __construct(
        public readonly CancellationPolicy $cancellation,
        public readonly ReschedulePolicy $reschedule,
    ) {
    }

    public function cancel(int $start, int $now, Money $paid): Decision
    {
        return $this->cancellation->decide($start, $now, $paid);
    }

    public function reschedule(int $start, int $now, int $times): Decision
    {
        return $this->reschedule->decide($start, $now, $times);
    }
}
