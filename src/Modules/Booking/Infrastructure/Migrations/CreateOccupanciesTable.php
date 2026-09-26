<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;

/**
 * The time each staff member and resource is taken, by a hold or an
 * appointment (data-model §2, ADR-004), in UTC with buffers included. Made
 * before the other booking tables (T2.1) because availability reads it.
 *
 * Beyond the data model: variant_id and staff_id tell group sessions apart
 * (implementation-notes §4.6), and expires_at, a hold's expiry (NULL for an
 * appointment), lets the overlap query skip expired holds without a join.
 */
final class CreateOccupanciesTable implements Migration
{
    public function up(Db $db): void
    {
        $db->createTable(
            Tables::name('occupancies'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            owner_id BIGINT UNSIGNED NOT NULL,
            lock_key VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            variant_id BIGINT UNSIGNED NULL,
            staff_id BIGINT UNSIGNED NULL,
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            seats SMALLINT UNSIGNED NOT NULL,
            expires_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY lock_span (lock_key, start_at, end_at),
            KEY owner (owner_type, owner_id)'
        );
    }
}
