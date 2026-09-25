<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Catalog;

use Vaqtyar\Kernel\Tables;

/**
 * For catalog tests that commit for real (TestCase with RealDatabase):
 * saving a service opens its own transaction, which would silently commit
 * a WP_UnitTestCase's (implementation-notes §5). They empty the tables
 * after themselves.
 */
trait CatalogTables
{
    private function emptyCatalogTables(): void
    {
        $tables = [
            'locations',
            'staff',
            'resources',
            'service_categories',
            'services',
            'service_variants',
            'service_staff',
            'service_resources',
            'extras',
        ];
        foreach ($tables as $table) {
            $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name($table));
        }
    }
}
