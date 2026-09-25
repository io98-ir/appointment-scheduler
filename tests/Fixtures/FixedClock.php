<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Fixtures;

use DateTimeImmutable;
use DateTimeZone;
use Vaqtyar\Shared\Domain\Clock;

/**
 * A clock that stays where a test puts it.
 */
final class FixedClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $now)
    {
        $this->now = new DateTimeImmutable($now, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(\sprintf('%+d seconds', $seconds));
    }
}
