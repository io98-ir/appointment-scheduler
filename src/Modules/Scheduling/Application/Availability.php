<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Application;

/**
 * The answer to an availability query: days of the location, whose time
 * zone gives the local times.
 */
final class Availability
{
    /**
     * @param list<DayAvailability> $days By date.
     */
    public function __construct(public readonly \DateTimeZone $timezone, public readonly array $days)
    {
    }
}
