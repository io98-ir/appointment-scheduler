<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;

/**
 * The waiting list: a customer who found a day full asks to be told when a
 * time opens on it. No foreign keys (ADR-006), so every *_id column has its
 * own index; the staff member is NULL when any will do.
 */
final class CreateWaitlistTable implements Migration
{
    public function up(Db $db): void
    {
        $db->createTable(
            Tables::name('waitlist'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            variant_id BIGINT UNSIGNED NOT NULL,
            staff_id BIGINT UNSIGNED NULL,
            wanted_date DATE NOT NULL,
            page_url VARCHAR(500) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            notified_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY status_date (status, wanted_date),
            KEY customer_id (customer_id),
            KEY location_id (location_id),
            KEY variant_id (variant_id)'
        );
    }
}
