<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Booking;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Infrastructure\Migrations\CreateBookingTables;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * The plugin booted with the Booking module, so its migrations have run on
 * the test database (TestCase, not WP_UnitTestCase: implementation-notes §5).
 */
final class CreateBookingTablesTest extends TestCase
{
    use RealDatabase;

    private const TABLES = [
        'occupancies',
        'holds',
        'appointments',
        'appointment_extras',
        'appointment_history',
        'appointment_answers',
        'fields',
        'labels',
        'price_rules',
        'policies',
        'coupons',
    ];

    public function testBootCreatedEveryBookingTableAsInnoDb(): void
    {
        $engines = [];
        foreach (self::TABLES as $table) {
            $engines[$table] = $this->realDb()->getVar(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                Tables::name($table)
            );
        }

        self::assertSame(\array_fill_keys(self::TABLES, 'InnoDB'), $engines);
    }

    /**
     * Running it again, as a retry after a half-done run would, keeps the
     * schema; the unique keys are what stop a duplicate code or token.
     */
    public function testRunningItAgainKeepsTheSchemaAndUniqueKeys(): void
    {
        (new CreateBookingTables())->up($this->realDb());

        self::assertSame(
            ['bigint', 'bigint', 'no'],
            [
                $this->column('appointments', 'price_total', 'COLUMN_TYPE'),
                $this->column('coupons', 'value', 'COLUMN_TYPE'),
                $this->column('policies', 'service_id', 'IS_NULLABLE'),
            ]
        );
        self::assertSame(
            ['1', '1', '1', '1', '2', '2'],
            [
                $this->uniqueColumns('appointments', 'uuid'),
                $this->uniqueColumns('appointments', 'code'),
                $this->uniqueColumns('holds', 'token_hash'),
                $this->uniqueColumns('coupons', 'code'),
                $this->uniqueColumns('policies', 'type_service'),
                // A composite index for the admin list by status.
                $this->indexColumns('appointments', 'status_start'),
            ]
        );
    }

    /**
     * A coupon code can be Persian, and raw SQL finds it: the table has no
     * ascii column for wpdb to judge its charset by (implementation-notes §4.5).
     */
    public function testAPersianCouponCodeIsFoundByRawSql(): void
    {
        $db = $this->realDb();
        $table = Tables::name('coupons');
        $db->insert($table, [
            'code' => 'نوروز',
            'type' => 'percent',
            'value' => 10,
            'status' => 'active',
            'created_at' => '2026-09-26 10:00:00',
            'updated_at' => '2026-09-26 10:00:00',
        ]);
        try {
            self::assertSame('10', $db->getVar('SELECT value FROM %i WHERE code = %s', $table, 'نوروز'));
        } finally {
            $db->execute('DELETE FROM %i WHERE code = %s', $table, 'نوروز');
        }
    }

    /**
     * Persian text in a JSON column and a TEXT column survives a round trip.
     */
    public function testAHistoryRowKeepsPersianText(): void
    {
        $db = $this->realDb();
        $table = Tables::name('appointment_history');
        $db->insert($table, [
            'appointment_id' => 987654,
            'action' => 'cancel',
            'from_status' => 'confirmed',
            'to_status' => 'cancelled',
            'changes' => '{"note":"لغو به درخواست مشتری"}',
            'actor_type' => 'staff',
            'actor_id' => 1,
            'reason' => 'بیماری',
            'created_at' => '2026-09-26 10:00:00',
        ]);
        try {
            self::assertSame(
                ['بیماری', 'لغو به درخواست مشتری'],
                [
                    $db->getVar('SELECT reason FROM %i WHERE appointment_id = %d', $table, 987654),
                    $db->getVar(
                        'SELECT JSON_UNQUOTE(JSON_EXTRACT(changes, %s)) FROM %i WHERE appointment_id = %d',
                        '$.note',
                        $table,
                        987654
                    ),
                ]
            );
        } finally {
            $db->execute('DELETE FROM %i WHERE appointment_id = %d', $table, 987654);
        }
    }

    private function uniqueColumns(string $table, string $index): ?string
    {
        return $this->realDb()->getVar(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s AND NON_UNIQUE = 0',
            Tables::name($table),
            $index
        );
    }

    private function indexColumns(string $table, string $index): ?string
    {
        return $this->realDb()->getVar(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s',
            Tables::name($table),
            $index
        );
    }

    /**
     * @param 'COLUMN_TYPE'|'IS_NULLABLE' $field
     */
    private function column(string $table, string $column, string $field): ?string
    {
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
