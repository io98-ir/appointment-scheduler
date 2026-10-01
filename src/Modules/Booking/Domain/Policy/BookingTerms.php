<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Policy;

/**
 * What a service asks of a customer's own booking besides its time: what
 * to pay online, whether staff approve it, and when it can be booked. The
 * service's own policy of each type wins over the global one, which wins
 * over doing nothing (WpdbPolicyReader).
 */
final class BookingTerms
{
    public function __construct(
        public readonly DepositPolicy $deposit,
        public readonly ApprovalPolicy $approval,
        public readonly BookingWindowPolicy $window,
    ) {
    }

    public static function lenient(): self
    {
        return new self(DepositPolicy::lenient(), ApprovalPolicy::lenient(), BookingWindowPolicy::lenient());
    }
}
