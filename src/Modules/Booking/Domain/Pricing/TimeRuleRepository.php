<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

/**
 * The time rows of price_rules for the admin CRUD screen (T3.5): flat, by
 * row id, not the booking-time read path (PricingReader).
 */
interface TimeRuleRepository
{
    /**
     * @return list<TimeRuleDefinition> by priority (highest first), then id.
     */
    public function all(): array;

    public function find(int $id): ?TimeRuleDefinition;

    /**
     * Inserts when $definition->id is 0, else replaces the stored row.
     */
    public function save(TimeRuleDefinition $definition): TimeRuleDefinition;

    public function delete(int $id): void;
}
