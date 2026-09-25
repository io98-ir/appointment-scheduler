<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Catalog\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Catalog\Domain\Color;
use Vaqtyar\Modules\Catalog\Domain\Name;
use Vaqtyar\Modules\Catalog\Domain\Slug;

final class ValuesTest extends TestCase
{
    use AssertsInvalidValue;

    public function testANameIsTrimmedAndKeepsPersianText(): void
    {
        self::assertSame('دکتر سارا کریمی', Name::fromInput("  دکتر سارا کریمی\t")->value);
        self::assertSame('Room 1', (string) Name::fromInput('Room 1'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'newline inside (lands in SMS and headers)' => ["Sara\nKarimi"];
        yield 'too long' => [\str_repeat('ن', Name::MAX_LENGTH + 1)];
    }

    /**
     * @dataProvider invalidNames
     */
    public function testRejectsInvalidNames(string $input): void
    {
        self::assertInvalid('invalid_name', static fn () => Name::fromInput($input));
    }

    public function testTheLongestNameFitsInCharactersNotBytes(): void
    {
        self::assertSame(Name::MAX_LENGTH, \mb_strlen(Name::fromInput(\str_repeat('ن', Name::MAX_LENGTH))->value));
    }

    public function testAColorIsAHexTripletStoredInLowercase(): void
    {
        self::assertSame('#1a2b3c', (string) Color::fromInput(' #1A2B3C '));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidColors(): iterable
    {
        yield 'no hash' => ['1a2b3c'];
        yield 'short form' => ['#abc'];
        yield 'with alpha' => ['#1a2b3c4d'];
        yield 'not hex' => ['#12345g'];
        yield 'css name' => ['red'];
        yield 'css injection' => ['#123456;background:url(x)'];
    }

    /**
     * @dataProvider invalidColors
     */
    public function testRejectsAnythingButAHexTriplet(string $input): void
    {
        self::assertInvalid('invalid_color', static fn () => Color::fromInput($input));
    }

    public function testASlugIsLowercaseLettersDigitsAndSingleSeparators(): void
    {
        self::assertSame('room', Slug::fromInput('room')->value);
        self::assertSame('laser-2_b', Slug::fromInput(' Laser-2_B ')->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSlugs(): iterable
    {
        yield 'empty' => [''];
        yield 'space inside' => ['dental chair'];
        yield 'persian' => ['اتاق'];
        yield 'leading separator' => ['-room'];
        yield 'trailing separator' => ['room_'];
        yield 'double separator' => ['room--1'];
        yield 'too long' => [\str_repeat('a', Slug::MAX_LENGTH + 1)];
    }

    /**
     * @dataProvider invalidSlugs
     */
    public function testRejectsInvalidSlugs(string $input): void
    {
        self::assertInvalid('invalid_slug', static fn () => Slug::fromInput($input));
    }
}
