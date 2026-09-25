<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Database;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\KernelException;

/**
 * Commit, rollback and the ADR-004 retry policy, against a recording $wpdb.
 * The real InnoDB behaviour is covered by the integration suite.
 */
final class TransactionTest extends TestCase
{
    use FakesWpdb;

    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transaction = new Transaction(new Db($this->fakeWpdb()));
    }

    protected function tearDown(): void
    {
        $this->tearDownWpdb();
        parent::tearDown();
    }

    public function testCommitsAndReturnsWhatTheWorkReturns(): void
    {
        self::assertSame(42, $this->transaction->run(static fn (): int => 42));
        self::assertSame(['START TRANSACTION', 'COMMIT'], $this->queries);
    }

    public function testRollsBackAndRethrowsWhenTheWorkThrows(): void
    {
        $error = new \DomainException('slot taken');
        $attempts = 0;

        $thrown = $this->thrownBy(static function () use ($error, &$attempts): void {
            $attempts++;
            throw $error;
        });

        self::assertSame($error, $thrown);
        self::assertSame(1, $attempts);
        self::assertSame(['START TRANSACTION', 'ROLLBACK'], $this->queries);
    }

    /**
     * @dataProvider retryableErrors
     */
    public function testRunsTheWorkAgainAfterADeadlockOrLockWaitTimeout(int $errno): void
    {
        $attempts = 0;

        $result = $this->transaction->run(static function () use ($errno, &$attempts): string {
            if (1 === ++$attempts) {
                throw DbException::queryFailed('lock', $errno);
            }

            return 'booked';
        });

        self::assertSame('booked', $result);
        self::assertSame(2, $attempts);
        self::assertSame(['START TRANSACTION', 'ROLLBACK', 'START TRANSACTION', 'COMMIT'], $this->queries);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function retryableErrors(): iterable
    {
        yield 'deadlock' => [DbException::DEADLOCK];
        yield 'lock wait timeout' => [DbException::LOCK_WAIT_TIMEOUT];
    }

    public function testGivesUpAfterThreeRetries(): void
    {
        $attempts = 0;
        $error = DbException::queryFailed('Deadlock found', DbException::DEADLOCK);

        $thrown = $this->thrownBy(static function () use ($error, &$attempts): void {
            $attempts++;
            throw $error;
        });

        self::assertSame($error, $thrown);
        self::assertSame(4, $attempts);
        self::assertSame(4, \count(\array_keys($this->queries, 'ROLLBACK', true)));
        self::assertNotContains('COMMIT', $this->queries);
    }

    public function testDoesNotRetryOtherDatabaseErrors(): void
    {
        $attempts = 0;

        $thrown = $this->thrownBy(static function () use (&$attempts): void {
            $attempts++;
            throw DbException::queryFailed('Duplicate entry', 1062);
        });

        self::assertInstanceOf(DbException::class, $thrown);
        self::assertSame(1062, $thrown->errno);
        self::assertSame(1, $attempts);
    }

    public function testRefusesANestedTransactionAndRollsBackTheOuterOne(): void
    {
        $thrown = $this->thrownBy(fn (): int => $this->transaction->run(static fn (): int => 1));

        self::assertInstanceOf(KernelException::class, $thrown);
        self::assertStringContainsString('already open', $thrown->getMessage());

        // A second START TRANSACTION would have committed the outer one.
        self::assertSame(['START TRANSACTION', 'ROLLBACK'], $this->queries);
    }

    public function testCanRunAgainAfterAFailedTransaction(): void
    {
        $this->thrownBy(static function (): void {
            throw new \RuntimeException('failed');
        });

        self::assertSame('ok', $this->transaction->run(static fn (): string => 'ok'));
    }

    public function testAFailedRollbackKeepsTheOriginalErrorAsPrevious(): void
    {
        $this->respondWith(
            fn (string $sql): bool => 'ROLLBACK' === $sql ? $this->failWith('MySQL server has gone away') : true
        );
        $cause = new \RuntimeException('work failed');

        $thrown = $this->thrownBy(static function () use ($cause): void {
            throw $cause;
        });

        self::assertInstanceOf(DbException::class, $thrown);
        self::assertSame('MySQL server has gone away', $thrown->detail);
        self::assertSame($cause, $thrown->getPrevious());
    }

    public function testACommitThatFailsIsRolledBack(): void
    {
        $this->respondWith(fn (string $sql): bool => 'COMMIT' === $sql ? $this->failWith('Lost connection') : true);

        $this->expectException(DbException::class);

        try {
            $this->transaction->run(static fn (): int => 1);
        } finally {
            self::assertSame(['START TRANSACTION', 'COMMIT', 'ROLLBACK'], $this->queries);
        }
    }

    /**
     * Runs $work in a transaction and returns what it threw.
     */
    private function thrownBy(callable $work): \Throwable
    {
        try {
            $this->transaction->run($work);
        } catch (\Throwable $e) {
            return $e;
        }

        self::fail('Nothing was thrown.');
    }
}
