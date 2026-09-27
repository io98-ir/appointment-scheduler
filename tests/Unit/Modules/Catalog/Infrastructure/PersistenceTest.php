<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Catalog\Infrastructure;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Row;

/**
 * The parts of the catalog storage that need no database. The SQL runs in
 * the integration suite.
 */
final class PersistenceTest extends TestCase
{
    public function testARowReadsItsColumnsAsPhpTypes(): void
    {
        $row = new Row(['id' => '7', 'name' => 'Main', 'phone' => null, 'sort' => '0']);

        self::assertSame(
            [7, 'Main', null, null, 0],
            [
                $row->int('id'),
                $row->string('name'),
                $row->stringOrNull('phone'),
                $row->intOrNull('phone'),
                $row->int('sort'),
            ]
        );
    }

    public function testAMissingColumnIsABug(): void
    {
        $this->expectException(\LogicException::class);
        (new Row(['id' => '7']))->string('name');
    }

    public function testNullInANonNullColumnIsABug(): void
    {
        $this->expectException(\LogicException::class);
        (new Row(['name' => null]))->string('name');
    }
}
