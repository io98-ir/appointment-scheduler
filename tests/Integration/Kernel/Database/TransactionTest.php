<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel\Database;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Tables;

/**
 * Real InnoDB: commit, rollback, and a retry after a lock wait timeout caused
 * by a second connection.
 */
final class TransactionTest extends TestCase
{
    use RealDatabase;

    private Db $db;
    private Transaction $transaction;
    private string $table;
    private ?\mysqli $other = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->realDb();
        $this->transaction = new Transaction($this->db);
        $this->table = Tables::name('test_slots');
        $this->dropTable($this->table);
        $this->db->createTable(
            $this->table,
            'id BIGINT UNSIGNED NOT NULL, taken TINYINT NOT NULL DEFAULT 0, PRIMARY KEY (id)'
        );
        $this->db->insert($this->table, ['id' => 1]);
    }

    protected function tearDown(): void
    {
        $this->other?->close();
        $this->db->execute('SET SESSION innodb_lock_wait_timeout = DEFAULT');
        $this->dropTable($this->table);
        parent::tearDown();
    }

    public function testCommittedWorkIsVisibleToOtherConnections(): void
    {
        $this->transaction->run(fn (): int => $this->db->update($this->table, ['taken' => 1], ['id' => 1]));

        self::assertSame('1', $this->takenAsSeenByAnotherConnection());
    }

    public function testWorkThatThrowsIsRolledBack(): void
    {
        try {
            $this->transaction->run(function (): void {
                $this->db->update($this->table, ['taken' => 1], ['id' => 1]);
                throw new \DomainException('slot_unavailable');
            });
        } catch (\DomainException) {
            // Expected: the row afterwards is what matters.
        }

        self::assertSame('0', $this->takenAsSeenByAnotherConnection());
    }

    public function testRetriesAfterALockWaitTimeout(): void
    {
        $this->lockRowFromAnotherConnection();
        $this->db->execute('SET SESSION innodb_lock_wait_timeout = 1');
        $attempts = 0;

        $taken = $this->transaction->run(function () use (&$attempts): ?string {
            if (2 === ++$attempts) {
                // The other request finishes; the retry gets the row.
                $this->other?->query('ROLLBACK');
            }

            return $this->db->getVar('SELECT taken FROM %i WHERE id = %d FOR UPDATE', $this->table, 1);
        });

        self::assertSame(2, $attempts);
        self::assertSame('0', $taken);
    }

    public function testGivesUpWhenTheLockStaysTaken(): void
    {
        $this->lockRowFromAnotherConnection();
        $this->db->execute('SET SESSION innodb_lock_wait_timeout = 1');
        $attempts = 0;

        try {
            $this->transaction->run(function () use (&$attempts): ?string {
                $attempts++;

                return $this->db->getVar('SELECT taken FROM %i WHERE id = %d FOR UPDATE', $this->table, 1);
            });
        } catch (DbException $e) {
            self::assertSame(DbException::LOCK_WAIT_TIMEOUT, $e->errno);
            self::assertSame(4, $attempts);

            return;
        }
        self::fail('The lock wait timeout was swallowed.');
    }

    public function testALostConnectionEndsTheTransactionInsteadOfGoingOnWithoutIt(): void
    {
        $this->other = $this->otherConnection();

        try {
            $this->transaction->run(function (): void {
                $id = (int) $this->db->getVar('SELECT CONNECTION_ID()');
                // The server drops our connection, as on a restart or failover.
                $this->other?->query("KILL {$id}");
                $this->db->update($this->table, ['taken' => 1], ['id' => 1]);
            });
        } catch (DbException $e) {
            // 2006 when wpdb reconnected and replayed the statement, or the
            // client's own error when it did not: never a silent success.
            self::assertNotSame(0, $e->errno);

            return;
        }
        self::fail('The transaction went on over a new connection.');
    }

    private function lockRowFromAnotherConnection(): void
    {
        $this->other = $this->otherConnection();
        $this->other->query('START TRANSACTION');
        $this->other->query("SELECT id FROM `{$this->table}` WHERE id = 1 FOR UPDATE");
    }

    private function takenAsSeenByAnotherConnection(): string
    {
        $this->other ??= $this->otherConnection();
        $result = $this->other->query("SELECT taken FROM `{$this->table}` WHERE id = 1");
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_row();
        self::assertIsArray($row);

        return (string) $row[0];
    }
}
