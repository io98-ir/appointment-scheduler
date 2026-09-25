<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Database;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Kernel\KernelException;

/**
 * How Db drives $wpdb: prepare only with values, typed formats, and an
 * exception for every failure. Real SQL runs in the integration suite.
 */
final class DbTest extends TestCase
{
    use FakesWpdb;

    private Db $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Db($this->fakeWpdb());
    }

    protected function tearDown(): void
    {
        $this->tearDownWpdb();
        parent::tearDown();
    }

    public function testExecuteWithoutValuesDoesNotPrepare(): void
    {
        // wpdb::prepare() without values is a _doing_it_wrong() notice.
        $this->wpdb->shouldNotReceive('prepare');

        self::assertSame(0, $this->db->execute('START TRANSACTION'));
    }

    public function testExecutePreparesTheValuesAndReturnsAffectedRows(): void
    {
        $this->respondWith(static fn (): int => 3);

        self::assertSame(3, $this->db->execute('DELETE FROM t WHERE a = %s AND b = %d', 'x', 7));
        self::assertSame(["DELETE FROM t WHERE a = 'x' AND b = 7"], $this->queries);
    }

    public function testGetVarReturnsTheValueOrNull(): void
    {
        $this->respondWith(static fn (string $sql): ?string => \str_contains($sql, "'yes'") ? '1' : null);

        self::assertSame('1', $this->db->getVar('SELECT 1 FROM t WHERE a = %s', 'yes'));
        self::assertNull($this->db->getVar('SELECT 1 FROM t WHERE a = %s', 'no'));
    }

    public function testAFailedQueryThrowsWithoutTheServerTextInTheMessage(): void
    {
        $this->respondWith(fn (): bool => $this->failWith("Duplicate entry '09121234567' for key 'phone'"));

        try {
            $this->db->execute('INSERT INTO t VALUES (1)');
            self::fail('The failure was silent.');
        } catch (DbException $e) {
            // The server quotes the value, which may be user input (implementation-notes §6).
            self::assertStringNotContainsString('0912', $e->getMessage());
            self::assertSame("Duplicate entry '09121234567' for key 'phone'", $e->detail);
        }
    }

    public function testAnErrorOnAQueryThatReturnsNothingStillThrows(): void
    {
        $this->respondWith(fn (): bool => $this->failWith("Table 'wp_x' doesn't exist"));

        $this->expectException(DbException::class);

        $this->db->getVar('SELECT a FROM wp_x');
    }

    public function testRestoresTheWpdbErrorDisplayEvenWhenAQueryFails(): void
    {
        $this->respondWith(fn (): bool => $this->failWith('error'));
        $this->wpdb->shouldReceive('hide_errors')->once()->andReturn(true);
        $this->wpdb->shouldReceive('show_errors')->once()->with(true);

        $this->expectException(DbException::class);

        $this->db->execute('SELECT 1');
    }

    public function testInsertPassesTypedFormatsAndReturnsTheNewId(): void
    {
        $this->wpdb->shouldReceive('insert')
            ->once()
            ->with('wp_items', ['name' => 'Ali', 'price' => 150000, 'note' => null], ['%s', '%d', '%s'])
            ->andReturnUsing(function (): int {
                $this->wpdb->insert_id = 12;

                return 1;
            });

        self::assertSame(12, $this->db->insert('wp_items', ['name' => 'Ali', 'price' => 150000, 'note' => null]));
    }

    public function testInsertThatWpdbRefusesSilentlyStillThrows(): void
    {
        // wpdb returns false with no error text when a value does not fit the column's charset.
        $this->wpdb->shouldReceive('insert')->andReturn(false);

        $this->expectException(DbException::class);

        $this->db->insert('wp_items', ['name' => 'x']);
    }

    public function testUpdateReturnsTheChangedRows(): void
    {
        $this->wpdb->shouldReceive('update')
            ->once()
            ->with('wp_items', ['price' => 5], ['id' => 3, 'deleted_at' => null], ['%d'], ['%d', '%s'])
            ->andReturn(1);

        self::assertSame(1, $this->db->update('wp_items', ['price' => 5], ['id' => 3, 'deleted_at' => null]));
    }

    public function testUpdateWithoutAConditionIsRefused(): void
    {
        $this->wpdb->shouldNotReceive('update');
        $this->expectException(KernelException::class);

        $this->db->update('wp_items', ['price' => 5], []);
    }

    /**
     * @dataProvider unsafeIdentifiers
     */
    public function testRejectsIdentifiersThatCannotGoIntoSqlUnquoted(string $table, string $column): void
    {
        $this->wpdb->shouldNotReceive('insert');
        $this->expectException(KernelException::class);

        $this->db->insert($table, [$column => 1]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsafeIdentifiers(): iterable
    {
        yield 'backtick in column' => ['wp_items', 'a`b'];
        yield 'space in column' => ['wp_items', 'a b'];
        yield 'dot in table' => ['db.wp_items', 'a'];
        yield 'empty table' => ['', 'a'];
    }

    public function testCreatesAnInnoDbTableInTheSiteCharset(): void
    {
        $this->wpdb->shouldReceive('get_charset_collate')
            ->andReturn('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->respondWith(
            static fn (string $sql): string|bool => \str_starts_with($sql, 'SELECT ENGINE') ? 'InnoDB' : true
        );

        $this->db->createTable('wp_items', 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)');

        self::assertSame(
            'CREATE TABLE IF NOT EXISTS `wp_items` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)) '
            . 'ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci',
            $this->queries[0]
        );
        self::assertStringContainsString("TABLE_NAME = 'wp_items'", $this->queries[1]);
    }

    /**
     * @dataProvider notInnoDb
     */
    public function testRefusesATableThatIsNotInnoDb(?string $engine, string $shown): void
    {
        // MySQL substituted another engine silently, or the table existed already.
        $this->wpdb->shouldReceive('get_charset_collate')->andReturn('');
        $this->respondWith(
            static fn (string $sql): string|bool|null => \str_starts_with($sql, 'SELECT ENGINE') ? $engine : true
        );

        $this->expectException(DbException::class);
        $this->expectExceptionMessage("uses the {$shown} storage engine");

        $this->db->createTable('wp_items', 'id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id)');
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function notInnoDb(): iterable
    {
        yield 'MyISAM' => ['MyISAM', 'MyISAM'];
        yield 'not visible' => [null, 'unknown'];
    }

    public function testCreateTableRejectsAnUnsafeName(): void
    {
        $this->wpdb->shouldNotReceive('query');
        $this->expectException(KernelException::class);

        $this->db->createTable('wp_items` (x INT); DROP TABLE wp_users; --', 'id INT');
    }

    public function testFromGlobalsNeedsWpdb(): void
    {
        unset($GLOBALS['wpdb']);
        $this->expectException(KernelException::class);

        Db::fromGlobals();
    }
}
