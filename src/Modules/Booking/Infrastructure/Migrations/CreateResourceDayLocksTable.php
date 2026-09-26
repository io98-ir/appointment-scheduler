<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;

/**
 * The rows a booking locks with FOR UPDATE (ADR-004), one per calendar and
 * UTC day. Nothing else is stored: the row is only something to lock.
 *
 * Beyond the data model: the day is the UTC date, not the local one, since
 * the locker needs no timezone to cover every day an occupancy touches;
 * and the table belongs to Booking, whose ResourceLocker is its only user.
 */
final class CreateResourceDayLocksTable implements Migration
{
    public function up(Db $db): void
    {
        $db->createTable(
            Tables::name('resource_day_locks'),
            'lock_key VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            day DATE NOT NULL,
            PRIMARY KEY (lock_key, day)'
        );
    }
}
