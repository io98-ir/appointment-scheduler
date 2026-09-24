<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

use DateTimeImmutable;

/**
 * The only source of the current time (principles §3). Tests pass a fixed one.
 */
interface Clock
{
    /**
     * The current instant, in UTC.
     */
    public function now(): DateTimeImmutable;
}
