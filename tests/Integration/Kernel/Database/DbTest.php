<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel\Database;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Kernel\Tables;

final class DbTest extends TestCase
{
    use RealDatabase;

    private Db $db;
    private string $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->realDb();
        $this->table = Tables::name('test_items');
        $this->dropTable($this->table);
        $this->db->createTable(
            $this->table,
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(8) NOT NULL,
            price BIGINT NOT NULL,
            note VARCHAR(20) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)'
        );
    }

    protected function tearDown(): void
    {
        $this->dropTable($this->table);
        parent::tearDown();
    }

    public function testInsertUpdateAndReadBackTypedValues(): void
    {
        // More than 2^31 rial: money needs BIGINT and %d must not truncate it.
        $id = $this->db->insert($this->table, ['code' => 'A1', 'price' => 5_000_000_000, 'note' => null]);
        self::assertGreaterThan(0, $id);

        self::assertSame('5000000000', $this->db->getVar('SELECT price FROM %i WHERE id = %d', $this->table, $id));
        self::assertNull($this->db->getVar('SELECT note FROM %i WHERE id = %d', $this->table, $id));

        self::assertSame(1, $this->db->update($this->table, ['note' => 'paid'], ['id' => $id, 'note' => null]));
        self::assertSame('paid', $this->db->getVar('SELECT note FROM %i WHERE id = %d', $this->table, $id));
        // Same value again: MySQL reports no changed row.
        self::assertSame(0, $this->db->update($this->table, ['note' => 'paid'], ['id' => $id]));
    }

    public function testPlaceholdersQuoteHostileInput(): void
    {
        $this->db->insert($this->table, ['code' => 'A1', 'price' => 1]);

        self::assertNull($this->db->getVar('SELECT id FROM %i WHERE code = %s', $this->table, "x' OR '1'='1"));
    }

    public function testAFailedQueryCarriesTheMysqlErrorNumber(): void
    {
        $this->db->insert($this->table, ['code' => 'A1', 'price' => 1]);

        try {
            $this->db->insert($this->table, ['code' => 'A1', 'price' => 2]);
        } catch (DbException $e) {
            self::assertSame(1062, $e->errno);
            self::assertStringNotContainsString('A1', $e->getMessage());
            self::assertStringContainsString('A1', $e->detail);

            return;
        }
        self::fail('The duplicate was accepted.');
    }

    public function testAValueLongerThanTheColumnThrowsInsteadOfBeingCut(): void
    {
        $this->expectException(DbException::class);

        $this->db->insert($this->table, ['code' => 'TOO-LONG-CODE', 'price' => 1]);
    }

    public function testWpdbKeepsItsErrorDisplaySetting(): void
    {
        $wpdb = $this->wpdb();
        $before = $wpdb->show_errors;

        try {
            $this->db->execute('SELECT nope FROM %i', $this->table);
        } catch (DbException) {
            // Expected: the setting afterwards is what matters.
        }

        self::assertSame($before, $wpdb->show_errors);
    }
}
