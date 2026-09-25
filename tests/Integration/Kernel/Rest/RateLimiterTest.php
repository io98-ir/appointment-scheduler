<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel\Rest;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\RateLimiter;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Tests\Fixtures\FixedClock;

/**
 * Counting against the real rate_limits table, which the kernel's own
 * migration created when the plugin booted. Each test's rows are rolled back.
 */
final class RateLimiterTest extends \WP_UnitTestCase
{
    private FixedClock $clock;

    private RateLimiter $limiter;

    private Db $db;

    public function set_up(): void
    {
        parent::set_up();
        $this->clock = new FixedClock('2026-09-25 10:00:30');
        $this->db = Db::fromGlobals();
        $this->limiter = new RateLimiter($this->db, $this->clock);
    }

    public function testCountsEveryAttemptInTheWindow(): void
    {
        $rule = new RateLimit(3, 60);

        $waits = [];
        for ($i = 0; $i < 4; $i++) {
            $waits[] = $this->limiter->attempt('login:ip:203.0.113.7', $rule);
        }

        self::assertSame([0, 0, 0, 30], $waits);
        self::assertSame('4', $this->db->getVar('SELECT SUM(hits) FROM %i', Tables::name('rate_limits')));
    }

    public function testANewWindowStartsFromZero(): void
    {
        $rule = new RateLimit(1, 60);
        $this->limiter->attempt('login', $rule);
        self::assertSame(30, $this->limiter->attempt('login', $rule));

        $this->clock->advance(30);

        self::assertSame(0, $this->limiter->attempt('login', $rule));
    }

    public function testKeysAreCountedApart(): void
    {
        $rule = new RateLimit(1, 60);
        $this->limiter->attempt('login:ip:203.0.113.7', $rule);

        self::assertSame(0, $this->limiter->attempt('login:ip:198.51.100.4', $rule));
    }

    public function testANewWindowRemovesExpiredRows(): void
    {
        $rule = new RateLimit(5, 60);
        $this->limiter->attempt('a', $rule);
        $this->limiter->attempt('b', $rule);
        $this->clock->advance(60);

        $this->limiter->attempt('a', $rule);

        // Only the new window of "a" is left; "b" never came back but is gone too.
        self::assertSame('1', $this->db->getVar('SELECT COUNT(*) FROM %i', Tables::name('rate_limits')));
    }
}
