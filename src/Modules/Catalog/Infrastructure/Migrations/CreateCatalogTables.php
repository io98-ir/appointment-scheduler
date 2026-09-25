<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;

/**
 * The catalog tables (data-model §2). No foreign keys (ADR-006), so every
 * *_id column has its own index. Everything but the assignment and
 * requirement rows is soft deleted, since appointments keep pointing at it.
 */
final class CreateCatalogTables implements Migration
{
    public function up(Db $db): void
    {
        $db->createTable(
            Tables::name('locations'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            timezone VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            address TEXT NOT NULL,
            phone VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
            holiday_calendar VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
            status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id)'
        );
        $db->createTable(
            Tables::name('staff'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NULL,
            location_id BIGINT UNSIGNED NULL,
            name VARCHAR(191) NOT NULL,
            search_name VARCHAR(191) NOT NULL,
            title VARCHAR(191) NOT NULL,
            email VARCHAR(254) NULL,
            phone VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
            color CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            avatar_id BIGINT UNSIGNED NULL,
            bio TEXT NOT NULL,
            status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY wp_user_id (wp_user_id),
            KEY location_id (location_id),
            KEY avatar_id (avatar_id),
            KEY search_name (search_name)'
        );
        $db->createTable(
            Tables::name('resources'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            location_id BIGINT UNSIGNED NULL,
            group_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            name VARCHAR(191) NOT NULL,
            capacity SMALLINT UNSIGNED NOT NULL,
            status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY location_id (location_id),
            KEY group_key (group_key)'
        );
        $db->createTable(
            Tables::name('service_categories'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            color CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id)'
        );
        $this->createServiceTables($db);
    }

    private function createServiceTables(Db $db): void
    {
        $db->createTable(
            Tables::name('services'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id BIGINT UNSIGNED NULL,
            name VARCHAR(191) NOT NULL,
            description TEXT NOT NULL,
            image_id BIGINT UNSIGNED NULL,
            capacity SMALLINT UNSIGNED NOT NULL,
            status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY category_id (category_id),
            KEY image_id (image_id)'
        );
        $db->createTable(
            Tables::name('service_variants'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(191) NOT NULL,
            duration_min SMALLINT UNSIGNED NOT NULL,
            price BIGINT NOT NULL,
            buffer_before_min SMALLINT UNSIGNED NOT NULL,
            buffer_after_min SMALLINT UNSIGNED NOT NULL,
            slot_step_min SMALLINT UNSIGNED NULL,
            is_default TINYINT(1) NOT NULL,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY service_id (service_id)'
        );
        // variant_id is NULL for a service-wide assignment, and a UNIQUE key
        // allows repeated NULLs, so uniqueness is the Service aggregate's rule.
        $db->createTable(
            Tables::name('service_staff'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id BIGINT UNSIGNED NOT NULL,
            staff_id BIGINT UNSIGNED NOT NULL,
            variant_id BIGINT UNSIGNED NULL,
            price BIGINT NULL,
            duration_min SMALLINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY service_id (service_id),
            KEY staff_id (staff_id),
            KEY variant_id (variant_id)'
        );
        $db->createTable(
            Tables::name('service_resources'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id BIGINT UNSIGNED NOT NULL,
            resource_group_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            quantity SMALLINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY service_group (service_id, resource_group_key)'
        );
        $db->createTable(
            Tables::name('extras'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id BIGINT UNSIGNED NULL,
            name VARCHAR(191) NOT NULL,
            price BIGINT NOT NULL,
            duration_min SMALLINT UNSIGNED NOT NULL,
            max_qty SMALLINT UNSIGNED NOT NULL,
            status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY service_id (service_id)'
        );
    }
}
