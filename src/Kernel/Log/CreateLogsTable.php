<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Log;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;

/**
 * The Logger's table (data-model §2). Rows are never updated, so there is no
 * updated_at; created_at has its own index for the retention prune.
 */
final class CreateLogsTable implements Migration
{
    public function up(Db $db): void
    {
        $db->createTable(
            Tables::name('logs'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(16) NOT NULL,
            channel VARCHAR(32) NOT NULL,
            message TEXT NOT NULL,
            context JSON NULL,
            request_id CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY level_created_at (level, created_at),
            KEY created_at (created_at)'
        );
    }
}
