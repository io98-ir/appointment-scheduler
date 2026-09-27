<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

/**
 * What a typed search matched: the tracking code it spells, or one of the
 * customers it names. An appointment matches on either.
 */
final class AppointmentSearch
{
    /**
     * @param list<int> $customerIds
     */
    public function __construct(
        public readonly ?string $code,
        public readonly array $customerIds,
    ) {
    }

    public function matchesNothing(): bool
    {
        return null === $this->code && [] === $this->customerIds;
    }
}
