<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Catalog;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Catalog\Infrastructure\Migrations\CreateCatalogTables;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * The plugin booted with the Catalog module, so its migration has run on the
 * test database. TestCase, not WP_UnitTestCase, which would turn a CREATE
 * TABLE into a temporary table (implementation-notes §5).
 */
final class CreateCatalogTablesTest extends TestCase
{
    use RealDatabase;

    private const TABLES = [
        'locations',
        'staff',
        'resources',
        'service_categories',
        'services',
        'service_variants',
        'service_staff',
        'service_resources',
        'extras',
    ];

    public function testBootCreatedEveryCatalogTableAsInnoDb(): void
    {
        $engines = [];
        foreach (self::TABLES as $table) {
            $engines[$table] = $this->realDb()->getVar(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                Tables::name($table)
            );
        }

        // Not db_versions: MigratorTest deletes that option.
        self::assertSame(\array_fill_keys(self::TABLES, 'InnoDB'), $engines);
    }

    /**
     * Running it again, as a retry after a half-done run would, keeps the
     * schema the domain limits rely on.
     */
    public function testRunningItAgainKeepsTheSchema(): void
    {
        (new CreateCatalogTables())->up($this->realDb());

        self::assertSame(
            ['smallint unsigned', 'bigint', 'yes'],
            [
                $this->column('service_variants', 'duration_min', 'COLUMN_TYPE'),
                $this->column('service_variants', 'price', 'COLUMN_TYPE'),
                $this->column('service_staff', 'variant_id', 'IS_NULLABLE'),
            ]
        );
        self::assertSame(
            '2',
            $this->realDb()->getVar(
                'SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s AND NON_UNIQUE = 0',
                Tables::name('service_resources'),
                'service_group'
            )
        );
    }

    /**
     * Money is an integer in rials (ADR-010); a price above 2^31 must fit.
     */
    public function testAPriceColumnHoldsLargeRialAmounts(): void
    {
        $db = $this->realDb();
        $table = Tables::name('service_variants');
        $db->insert($table, [
            'service_id' => 1,
            'label' => 'long',
            'duration_min' => 90,
            'price' => 25_000_000_000,
            'buffer_before_min' => 0,
            'buffer_after_min' => 0,
            'slot_step_min' => null,
            'is_default' => 1,
            'created_at' => '2026-09-25 10:00:00',
            'updated_at' => '2026-09-25 10:00:00',
        ]);
        try {
            self::assertSame('25000000000', $db->getVar('SELECT price FROM %i WHERE label = %s', $table, 'long'));
        } finally {
            $db->execute('DELETE FROM %i WHERE label = %s', $table, 'long');
        }
    }

    /**
     * @param 'COLUMN_TYPE'|'IS_NULLABLE' $field
     */
    private function column(string $table, string $column, string $field): ?string
    {
        // MySQL 8.0.19+ drops the display width ("bigint(20)"), MariaDB and 5.7 keep it.
        $value = $this->realDb()->getVar(
            'SELECT %i FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            $field,
            Tables::name($table),
            $column
        );

        return null === $value ? null : \strtolower((string) \preg_replace('/\(\d+\)/', '', $value));
    }
}
