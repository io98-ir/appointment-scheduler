<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;

/**
 * payments and refunds (data-model §2). One row per attempt, unique per
 * gateway and authority so a repeated callback finds the same row. Refunds
 * are written from T5.2 on; the table is made now so it never needs a
 * second migration of this module.
 */
final class CreatePaymentTables implements Migration
{
    public function up(Db $db): void
    {
        $db->createTable(
            Tables::name('payments'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_id BIGINT UNSIGNED NOT NULL,
            gateway VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            amount BIGINT NOT NULL,
            status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            authority VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            ref_id VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NULL,
            card_mask VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
            raw JSON NULL,
            verified_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY gateway_authority (gateway, authority),
            KEY appointment_id (appointment_id),
            KEY status_created (status, created_at)'
        );
        $db->createTable(
            Tables::name('refunds'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            payment_id BIGINT UNSIGNED NOT NULL,
            appointment_id BIGINT UNSIGNED NOT NULL,
            amount BIGINT NOT NULL,
            method VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            reason TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY payment_id (payment_id),
            KEY appointment_id (appointment_id)'
        );
    }
}
