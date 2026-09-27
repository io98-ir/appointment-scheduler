<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;

/**
 * The customers table (data-model §2, Customers). The OTP and session
 * tables come with the login (T4.3).
 *
 * No ascii columns: the search sends Persian text in raw SQL, and wpdb
 * then checks it against the table's charset (implementation-notes §4.5).
 * A deleted customer's phone is NULL, so the unique index lets the number
 * sign up again.
 */
final class CreateCustomersTable implements Migration
{
    public function up(Db $db): void
    {
        $db->createTable(
            Tables::name('customers'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(26) NOT NULL,
            wp_user_id BIGINT UNSIGNED NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            search_name VARCHAR(201) NOT NULL,
            phone VARCHAR(20) NULL,
            email VARCHAR(254) NULL,
            birth_date DATE NULL,
            note TEXT NOT NULL,
            tags TEXT NOT NULL,
            status VARCHAR(32) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            UNIQUE KEY phone (phone),
            KEY wp_user_id (wp_user_id),
            KEY search_name (search_name(191))'
        );
    }
}
