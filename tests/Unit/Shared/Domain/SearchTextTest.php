<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\SearchText;

final class SearchTextTest extends TestCase
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
    public function testTextIsNormalizedForSearch(string $name, string $expected): void
    {
        self::assertSame($expected, SearchText::normalize($name));
    }
}
