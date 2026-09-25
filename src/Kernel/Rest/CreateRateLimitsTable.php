<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Rest;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;

/**
 * The RateLimiter's counters. The bucket is a hex HMAC, so ASCII is enough and
 * keeps the primary key small. Rows live for one window: no created_at or
 * updated_at (data-model §2).
 */
final class CreateRateLimitsTable implements Migration
{
    public function up(Db $db): void
    {
        $db->createTable(
            Tables::name('rate_limits'),
            'bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            window_start DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            hits INT UNSIGNED NOT NULL,
            PRIMARY KEY (bucket, window_start),
            KEY expires_at (expires_at)'
        );
    }
}
