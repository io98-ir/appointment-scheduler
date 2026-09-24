<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\SystemClock;

final class SystemClockTest extends TestCase
{
    public function testReturnsTheCurrentTimeInUtc(): void
    {
        $before = \time();
        $now = (new SystemClock())->now();
        $after = \time();

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $now->getTimestamp());
        self::assertLessThanOrEqual($after, $now->getTimestamp());
    }
}
