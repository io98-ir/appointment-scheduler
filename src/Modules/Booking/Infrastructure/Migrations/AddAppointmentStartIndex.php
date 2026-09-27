<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;

/**
 * An index on appointments.start_at alone (T2.8): the admin list with no
 * staff, customer or status filter is ordered by start, and the calendar
 * for every staff member reads a range of starts. The composite indexes
 * lead with another column and serve neither.
 */
final class AddAppointmentStartIndex implements Migration
{
    public function up(Db $db): void
    {
        $table = Tables::name('appointments');
        $exists = $db->getVar(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s',
            $table,
            'start_at'
        );
        if ('0' === $exists) {
            $db->execute('ALTER TABLE %i ADD KEY start_at (start_at)', $table);
        }
    }
}
