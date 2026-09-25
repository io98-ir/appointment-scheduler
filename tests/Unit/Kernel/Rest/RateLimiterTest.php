<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Rest;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\RateLimiter;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Tests\Fixtures\FixedClock;
use Vaqtyar\Tests\Unit\Kernel\Database\FakesWpdb;

/**
 * Window arithmetic and the statements sent. Real counting against MySQL is
 * in the integration suite.
 */
final class RateLimiterTest extends TestCase
{
    use FakesWpdb;

    private FixedClock $clock;

    private RateLimiter $limiter;

    /** The hit count the fake database reports for the current attempt. */
    private int $hits = 1;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('wp_salt')->justReturn('test-salt');
        $this->clock = new FixedClock('2026-09-25 10:00:30');
        $this->limiter = new RateLimiter(new Db($this->fakeWpdb()), $this->clock);
        $this->respondWith(function (string $sql): int|bool {
            if (\str_starts_with($sql, 'INSERT')) {
                // MySQL reports 1 for a new row and 2 for an updated one, and
                // wpdb keeps the LAST_INSERT_ID(expr) value as insert_id.
                $this->wpdb->insert_id = 1 === $this->hits ? 0 : $this->hits;

                return 1 === $this->hits ? 1 : 2;
            }

            return true;
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $this->tearDownWpdb();
        parent::tearDown();
    }

    public function testAllowsAttemptsUpToTheLimit(): void
    {
        $this->hits = 3;

        self::assertSame(0, $this->limiter->attempt('otp:ip:203.0.113.7', new RateLimit(3, 60)));
    }

    public function testRefusesAnAttemptOverTheLimitUntilTheWindowEnds(): void
    {
        $this->hits = 4;

        // The window is 10:00:00 to 10:01:00 and it is 10:00:30.
        self::assertSame(30, $this->limiter->attempt('otp:ip:203.0.113.7', new RateLimit(3, 60)));
    }

    public function testWindowsAreAlignedToTheirLength(): void
    {
        $this->limiter->attempt('otp:ip:203.0.113.7', new RateLimit(3, 600));

        self::assertStringContainsString(
            "'2026-09-25 10:00:00', '2026-09-25 10:10:00', 1",
            $this->queries[0]
        );
    }

    public function testCountsWithOneAtomicStatement(): void
    {
        $this->hits = 2;

        $this->limiter->attempt('otp:ip:203.0.113.7', new RateLimit(3, 60));

        // No second SELECT: a read/write-split drop-in could send it to a replica.
        self::assertCount(1, $this->queries);
        self::assertStringStartsWith('INSERT INTO `' . Tables::name('rate_limits') . '`', $this->queries[0]);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(hits + 1)', $this->queries[0]);
    }

    public function testStoresAKeyedHashNeverTheKeyItself(): void
    {
        // Keys hold phone numbers and IP addresses (principles §7: no PII at rest).
        $this->limiter->attempt('otp:phone:+989121234567', new RateLimit(3, 60));

        self::assertStringNotContainsString('989121234567', \implode("\n", $this->queries));
        self::assertMatchesRegularExpression("/VALUES \\('[0-9a-f]{64}'/", $this->queries[0]);
    }

    public function testTheSameKeyUnderAnotherWindowIsAnotherBucket(): void
    {
        $this->limiter->attempt('otp', new RateLimit(3, 60));
        $this->limiter->attempt('otp', new RateLimit(3, 600));

        self::assertNotSame($this->bucketOf($this->queries[0]), $this->bucketOf($this->queries[2]));
    }

    public function testANewWindowPrunesExpiredRows(): void
    {
        $this->hits = 1;

        $this->limiter->attempt('otp', new RateLimit(3, 60));

        self::assertCount(2, $this->queries);
        self::assertSame(
            'DELETE FROM `' . Tables::name('rate_limits') . "` WHERE expires_at <= '2026-09-25 10:00:30' LIMIT 100",
            $this->queries[1]
        );
    }

    public function testOtherDatabaseErrorsAreNotHidden(): void
    {
        $this->respondWith(fn (): bool => $this->failWith("Table 'rate_limits' doesn't exist"));

        $this->expectException(DbException::class);

        $this->limiter->attempt('otp', new RateLimit(3, 60));
    }

    private function bucketOf(string $sql): string
    {
        if (1 !== \preg_match("/VALUES \\('([0-9a-f]{64})'/", $sql, $match)) {
            self::fail('No bucket in: ' . $sql);
        }

        return $match[1];
    }
}
