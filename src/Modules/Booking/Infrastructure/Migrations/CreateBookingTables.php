<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;

/**
 * The booking tables besides occupancies (data-model §2). No foreign keys
 * (ADR-006), so every *_id column has its own index. Appointments are never
 * deleted, only cancelled, so nothing here has deleted_at.
 *
 * Beyond the data model: fields.key is field_key and fields.condition is
 * show_if, since KEY and CONDITION are reserved words in MySQL; a hold
 * keeps its location_id, which confirming it needs for the appointment; and
 * policies.service_id is 0, not NULL, for the global policy.
 */
final class CreateBookingTables implements Migration
{
    public function up(Db $db): void
    {
        $db->createTable(
            Tables::name('holds'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            variant_id BIGINT UNSIGNED NOT NULL,
            staff_id BIGINT UNSIGNED NOT NULL,
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            party_size SMALLINT UNSIGNED NOT NULL,
            extras JSON NOT NULL,
            price_quote JSON NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            KEY expires_at (expires_at),
            KEY location_id (location_id),
            KEY variant_id (variant_id),
            KEY staff_id (staff_id)'
        );
        $db->createTable(
            Tables::name('appointments'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            service_id BIGINT UNSIGNED NOT NULL,
            variant_id BIGINT UNSIGNED NOT NULL,
            staff_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            label_id BIGINT UNSIGNED NULL,
            payment_status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            source VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            local_date DATE NOT NULL,
            timezone VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            party_size SMALLINT UNSIGNED NOT NULL,
            price_total BIGINT NOT NULL,
            price_lines JSON NOT NULL,
            deposit_amount BIGINT NOT NULL DEFAULT 0,
            customer_note TEXT NOT NULL,
            internal_note TEXT NOT NULL,
            cancelled_at DATETIME NULL,
            cancel_reason TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            UNIQUE KEY code (code),
            KEY status_start (status, start_at),
            KEY customer_start (customer_id, start_at),
            KEY staff_start (staff_id, start_at),
            KEY location_id (location_id),
            KEY service_id (service_id),
            KEY variant_id (variant_id),
            KEY label_id (label_id),
            KEY created_by (created_by)'
        );
        $db->createTable(
            Tables::name('appointment_extras'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_id BIGINT UNSIGNED NOT NULL,
            extra_id BIGINT UNSIGNED NOT NULL,
            qty SMALLINT UNSIGNED NOT NULL,
            price BIGINT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY appointment_id (appointment_id),
            KEY extra_id (extra_id)'
        );
        // Written once, like logs, so no updated_at.
        $db->createTable(
            Tables::name('appointment_history'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            from_status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
            to_status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
            changes JSON NULL,
            actor_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            actor_id BIGINT UNSIGNED NULL,
            reason TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY appointment_id (appointment_id),
            KEY actor_id (actor_id)'
        );
        $db->createTable(
            Tables::name('appointment_answers'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_id BIGINT UNSIGNED NOT NULL,
            field_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            value TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY appointment_id (appointment_id)'
        );
        $db->createTable(
            Tables::name('fields'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            service_id BIGINT UNSIGNED NULL,
            field_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            label VARCHAR(191) NOT NULL,
            required TINYINT(1) NOT NULL DEFAULT 0,
            options JSON NULL,
            show_if JSON NULL,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY service_id (service_id)'
        );
        $db->createTable(
            Tables::name('labels'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            color CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id)'
        );
        $db->createTable(
            Tables::name('price_rules'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            service_id BIGINT UNSIGNED NULL,
            config JSON NOT NULL,
            priority INT NOT NULL DEFAULT 0,
            status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY service_id (service_id)'
        );
        // service_id 0 is the global policy: UNIQUE lets many NULLs in, 0 it does not.
        $db->createTable(
            Tables::name('policies'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            service_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            config JSON NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY type_service (type, service_id),
            KEY service_id (service_id)'
        );
        // No ascii column: code may be Persian, and one ascii column would make wpdb
        // reject raw SQL with Persian text on the whole table (implementation-notes §4.5).
        $db->createTable(
            Tables::name('coupons'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(64) NOT NULL,
            type VARCHAR(32) NOT NULL,
            value BIGINT NOT NULL,
            max_uses INT UNSIGNED NULL,
            used INT UNSIGNED NOT NULL DEFAULT 0,
            valid_from DATETIME NULL,
            valid_to DATETIME NULL,
            service_ids JSON NULL,
            status VARCHAR(32) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)'
        );
    }
}
