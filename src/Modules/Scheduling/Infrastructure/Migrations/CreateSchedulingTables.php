<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;

/**
 * The scheduling tables (data-model §2). Times of day are TIME ("24:00:00"
 * ends a day) and dates are local DATEs. Rows are deleted for real: nothing
 * points at them.
 */
final class CreateSchedulingTables implements Migration
{
    public function up(Db $db): void
    {
        $db->createTable(
            Tables::name('schedule_rules'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            owner_id BIGINT UNSIGNED NOT NULL,
            weekday TINYINT UNSIGNED NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            kind VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY owner (owner_type, owner_id)'
        );
        $db->createTable(
            Tables::name('schedule_exceptions'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            owner_id BIGINT UNSIGNED NOT NULL,
            local_date DATE NOT NULL,
            start_time TIME NULL,
            end_time TIME NULL,
            kind VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            note TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY owner_date (owner_type, owner_id, local_date)'
        );
        // One charset for every text column: the holiday upsert is raw SQL with
        // a Persian title, and wpdb checks raw SQL against the table's charset,
        // which it takes as ascii when ascii and utf8mb4 columns are mixed
        // (implementation-notes §4.5).
        $db->createTable(
            Tables::name('holidays'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            calendar VARCHAR(64) NOT NULL,
            local_date DATE NOT NULL,
            title VARCHAR(191) NOT NULL,
            source VARCHAR(32) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY calendar_date (calendar, local_date)'
        );
    }
}
