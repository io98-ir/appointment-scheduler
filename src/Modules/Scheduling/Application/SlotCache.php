<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Application;

/**
 * The computed days of availability, kept between requests when the site
 * has an object cache (architecture §11). Only offers are read from it:
 * a hold re-checks the database under locks (ADR-004).
 */
interface SlotCache
{
    /**
     * @return ?array<mixed> Null on a miss.
     */
    public function get(string $key): ?array;

    /**
     * @param array<mixed> $value
     */
    public function set(string $key, array $value): void;
}
