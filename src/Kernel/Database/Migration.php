<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Database;

/**
 * One schema change (data-model §3). Lives in the owning module's
 * Infrastructure/Migrations, named for what it does (CreateLogsTable); its
 * place in Module::migrations() is its version, so names carry no number.
 *
 * up() must be idempotent: MySQL commits DDL at once, so a migration that
 * fails halfway runs again from the start on the next attempt. Tables are
 * created with Db::createTable() and changed with explicit ALTER statements
 * guarded by an information_schema check, never with dbDelta().
 */
interface Migration
{
    public function up(Db $db): void;
}
