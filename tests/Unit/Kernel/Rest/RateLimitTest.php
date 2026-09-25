<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Rest;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\KernelException;
use Vaqtyar\Kernel\Rest\RateLimit;

final class RateLimitTest extends TestCase
{
    public function testKeepsTheLimitAndTheWindow(): void
    {
        $rule = new RateLimit(30, 60);

        self::assertSame(30, $rule->limit);
        self::assertSame(60, $rule->windowSeconds);
    }

    public function testRefusesALimitBelowOne(): void
    {
        $this->expectException(KernelException::class);

        new RateLimit(0, 60);
    }

    public function testRefusesAWindowBelowOneSecond(): void
    {
        $this->expectException(KernelException::class);

        new RateLimit(5, 0);
    }
}
