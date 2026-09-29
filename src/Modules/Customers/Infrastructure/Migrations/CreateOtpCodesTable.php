<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;

/**
 * The one-time codes of the phone login (T4.3): a keyed hash per code, its
 * expiry, guesses and use. Sessions are signed tokens and need no table.
 */
final class CreateOtpCodesTable implements Migration
{
    public function up(Db $db): void
    {
        $db->createTable(
            Tables::name('otp_codes'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            phone VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            consumed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY phone_created (phone, created_at),
            KEY created_at (created_at)'
        );
    }
}
