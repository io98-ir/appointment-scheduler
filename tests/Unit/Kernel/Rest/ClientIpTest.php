<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Rest;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Rest\ClientIp;

final class ClientIpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubs(['wp_unslash', 'sanitize_text_field']);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    public function testAnIpv4AddressIsItsOwnKey(): void
    {
        self::assertSame('203.0.113.7', ClientIp::fromString('203.0.113.7')->rateLimitKey());
    }

    public function testAnIpv6AddressCountsByItsSlash64Network(): void
    {
        // A single host usually gets a whole /64, so counting the address alone
        // would let it rotate addresses around the limit.
        $a = ClientIp::fromString('2001:db8:85a3:8d3:1319:8a2e:370:7348');
        $b = ClientIp::fromString('2001:0db8:85a3:08d3::1');

        self::assertSame('2001:db8:85a3:8d3::/64', $a->rateLimitKey());
        self::assertSame($a->rateLimitKey(), $b->rateLimitKey());
    }

    public function testAnIpv4MappedIpv6AddressCountsAsIpv4(): void
    {
        self::assertSame('203.0.113.7', ClientIp::fromString('::ffff:203.0.113.7')->rateLimitKey());
    }

    public function testAnInvalidAddressIsUnknown(): void
    {
        self::assertSame('unknown', ClientIp::fromString('not an ip')->rateLimitKey());
        self::assertSame('unknown', ClientIp::fromString('')->rateLimitKey());
    }

    public function testReadsRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.4';

        self::assertSame('198.51.100.4', ClientIp::fromRequest()->rateLimitKey());
    }

    public function testASiteBehindAProxyReplacesTheAddressThroughAFilter(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        Filters\expectApplied(Hooks::name('rest/client_ip'))
            ->once()
            ->with('10.0.0.1')
            ->andReturn('198.51.100.4');

        self::assertSame('198.51.100.4', ClientIp::fromRequest()->rateLimitKey());
    }

    public function testAFilterThatReturnsNoAddressMakesTheClientUnknown(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        Filters\expectApplied(Hooks::name('rest/client_ip'))->once()->andReturn(null);

        self::assertSame('unknown', ClientIp::fromRequest()->rateLimitKey());
    }

    public function testWithoutRemoteAddrTheClientIsUnknown(): void
    {
        self::assertSame('unknown', ClientIp::fromRequest()->rateLimitKey());
    }
}
