<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

/**
 * A time rule as a stored price_rules row (T3.5): the TimeRule the price
 * calculator reads, plus what only the admin screen needs.
 */
final class TimeRuleDefinition
{
    /**
     * @param int $id 0 for a rule not stored yet.
     * @param ?int $serviceId null applies to every service.
     * @param int $priority higher runs first when several rules match.
     */
    public function __construct(
        public readonly int $id,
        public readonly ?int $serviceId,
        public readonly int $priority,
        public readonly bool $active,
        public readonly TimeRule $rule,
    ) {
    }
}
