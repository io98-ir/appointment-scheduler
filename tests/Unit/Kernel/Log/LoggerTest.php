<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Log;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Log\Logger;
use Vaqtyar\Kernel\Log\LogLevel;
use Vaqtyar\Kernel\RequestId;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Shared\Domain\Calendar;
use Vaqtyar\Tests\Fixtures\FixedClock;
use Vaqtyar\Tests\Unit\Kernel\Database\FakesWpdb;

final class LoggerTest extends TestCase
{
    use FakesWpdb;

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    private RequestId $requestId;

    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('wp_json_encode')->alias(
            static fn (mixed $value, int $flags = 0): string|false => \json_encode($value, $flags)
        );
        $wpdb = $this->fakeWpdb();
        $wpdb->shouldReceive('insert')->andReturnUsing(function (string $table, array $data): int {
            self::assertSame(Tables::name('logs'), $table);
            /** @var array<string, mixed> $data */
            $this->rows[] = $data;

            return 1;
        })->byDefault();
        $this->requestId = new RequestId();
        $this->logger = new Logger(Db::fromGlobals(), new FixedClock('2026-09-25 10:00:30'), $this->requestId);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $this->tearDownWpdb();
        parent::tearDown();
    }

    public function testWritesALineWithTheRequestId(): void
    {
        $this->logger->warning('payments', 'Gateway timed out, trying the next one.', ['gateway' => 'zibal']);

        self::assertSame([[
            'level' => 'warning',
            'channel' => 'payments',
            'message' => 'Gateway timed out, trying the next one.',
            'context' => '{"gateway":"zibal"}',
            'request_id' => $this->requestId->value(),
            'created_at' => '2026-09-25 10:00:30',
        ]], $this->rows);
    }

    public function testEachLevelHasAShortcut(): void
    {
        $this->logger->error('a', 'x');
        $this->logger->warning('a', 'x');
        $this->logger->info('a', 'x');
        $this->logger->log(LogLevel::Info, 'a', 'x');

        self::assertSame(['error', 'warning', 'info', 'info'], \array_column($this->rows, 'level'));
    }

    public function testAnEmptyContextIsStoredAsNull(): void
    {
        $this->logger->info('booking', 'Hold expired.');

        self::assertNull($this->rows[0]['context']);
    }

    public function testMasksPersonalDataInTheMessageAndTheContext(): void
    {
        $this->logger->error('sms', 'Sending to 09121234567 failed.', [
            'recipient' => '+989121234567',
            'nested' => ['email' => 'ali@example.com', 'attempt' => 2],
        ]);

        self::assertSame('Sending to ***4567 failed.', $this->rows[0]['message']);
        self::assertSame(
            '{"recipient":"***4567","nested":{"email":"***@example.com","attempt":2}}',
            $this->rows[0]['context']
        );
    }

    public function testMasksPersonalDataInKeysToo(): void
    {
        $this->logger->warning('sms', 'Some recipients failed.', ['failed' => ['09121234567' => 'blocked']]);

        self::assertSame('{"failed":{"***4567":"blocked"}}', $this->rows[0]['context']);
    }

    public function testAnExceptionKeepsWhereItHappenedAndAMaskedMessage(): void
    {
        $e = new \RuntimeException("Duplicate entry '09121234567'", 1062);

        $this->logger->error('rest', 'A REST request failed.', ['exception' => $e]);

        $json = $this->rows[0]['context'];
        self::assertIsString($json);
        $context = \json_decode($json, true);
        self::assertSame([
            'exception' => [
                'class' => \RuntimeException::class,
                'code' => 1062,
                'message' => "Duplicate entry '***4567'",
                'file' => __FILE__,
                'line' => $e->getLine(),
            ],
        ], $context);
    }

    public function testOtherValuesAreReducedToSomethingSafeToStore(): void
    {
        $deep = ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 1]]]]]];

        $this->logger->info('x', 'y', [
            'enum' => Calendar::Jalali,
            'object' => new \stdClass(),
            'deep' => $deep,
            'null' => null,
            'float' => 1.5,
        ]);

        self::assertSame(
            '{"enum":"jalali","object":"stdClass","deep":{"a":{"b":{"c":{"d":"[too deep]"}}}},"null":null,"float":1.5}',
            $this->rows[0]['context']
        );
    }

    public function testALargeContextIsReplacedByAMarker(): void
    {
        $this->logger->info('x', 'y', ['blob' => \str_repeat('a', 20000)]);

        self::assertSame('{"truncated":true}', $this->rows[0]['context']);
    }

    public function testALongMessageIsCutAfterMasking(): void
    {
        $this->logger->info('x', \str_repeat('a', 995) . '09121234567');

        self::assertSame(\str_repeat('a', 995) . '***45', $this->rows[0]['message']);
    }

    public function testRemovesLinesPastTheRetention(): void
    {
        $this->logger->info('x', 'y');

        self::assertSame(
            ['DELETE FROM `' . Tables::name('logs') . "` WHERE created_at < '2026-08-26 10:00:30' LIMIT 100"],
            $this->queries
        );
    }

    public function testFallsBackToThePhpErrorLogWhenTheTableCannotBeWritten(): void
    {
        $this->wpdb->shouldReceive('insert')->andReturnUsing(fn (): bool => $this->failWith("Table doesn't exist"));

        $logged = $this->capturingErrorLog(function (): void {
            $this->logger->error('migrations', 'Mail to ali@example.com failed.', ['code' => 42]);
        });

        self::assertStringContainsString('[error] migrations: Mail to ***@example.com failed.', $logged);
        // With the context, which may be all there is to debug from.
        self::assertStringContainsString(' {"code":42}', $logged);
        self::assertStringContainsString($this->requestId->value(), $logged);
        self::assertStringNotContainsString('ali@', $logged);
        // No prune after a failed write.
        self::assertSame([], $this->queries);
    }

    public function testDoesNotPruneInsideATransaction(): void
    {
        $db = Db::fromGlobals();
        $logger = new Logger($db, new FixedClock('2026-09-25 10:00:30'), $this->requestId);
        $db->beginTransaction();

        $logger->info('booking', 'Override by an admin.');

        self::assertCount(1, $this->rows);
        self::assertSame(['START TRANSACTION'], $this->queries);
    }

    public function testAFailedPruneDoesNotLoseTheLine(): void
    {
        $this->respondWith(fn (): bool => $this->failWith('Lock wait timeout exceeded'));

        $logged = $this->capturingErrorLog(function (): void {
            $this->logger->info('x', 'y');
        });

        self::assertCount(1, $this->rows);
        self::assertSame('', $logged);
    }

    /**
     * @param \Closure(): void $action
     */
    private function capturingErrorLog(\Closure $action): string
    {
        $file = \tempnam(\sys_get_temp_dir(), 'log');
        self::assertIsString($file);
        $previous = \ini_set('error_log', $file);
        try {
            $action();
        } finally {
            \ini_set('error_log', (string) $previous);
        }
        $logged = (string) \file_get_contents($file);
        \unlink($file);

        return $logged;
    }
}
