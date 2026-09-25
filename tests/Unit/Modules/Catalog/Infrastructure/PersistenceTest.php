<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Catalog\Infrastructure;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\Row;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbStaffRepository;

/**
 * The parts of the catalog storage that need no database. The SQL runs in
 * the integration suite.
 */
final class PersistenceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'Arabic ya and kaf' => [
            "\u{0639}\u{0644}\u{064A} \u{0643}\u{0631}\u{064A}\u{0645}\u{064A}",
            'علی کریمی',
        ];
        yield 'zero-width non-joiner' => [
            "\u{0645}\u{062D}\u{0645}\u{062F}\u{200C}\u{0631}\u{0636}\u{0627}",
            'محمد رضا',
        ];
        yield 'diacritics and tatweel' => ["\u{0645}\u{064F}\u{062D}\u{0640}\u{0645}\u{0651}\u{062F}", 'محمد'];
        yield 'digits' => ['Room ۱۲ ٣', 'room 12 3'];
        yield 'case and spaces' => ["  Dr.   KARIMI\t", 'dr. karimi'];
    }

    /**
     * @dataProvider names
     */
    public function testTheSearchNameIsNormalized(string $name, string $expected): void
    {
        self::assertSame($expected, WpdbStaffRepository::searchName($name));
    }

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
