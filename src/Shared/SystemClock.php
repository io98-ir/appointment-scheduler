<?php

declare(strict_types=1);

namespace Vaqtyar\Shared;

use DateTimeImmutable;
use DateTimeZone;
use Vaqtyar\Shared\Domain\Clock;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
