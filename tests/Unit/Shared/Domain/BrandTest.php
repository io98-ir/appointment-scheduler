<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\Brand;
use Vaqtyar\Shared\Domain\InvalidValue;

final class BrandTest extends TestCase
{
    public function testTrimsAndLowercasesTheColour(): void
    {
        $brand = Brand::of('  Salon Nima ', ' https://example.test/logo.png ', ' #3858E9 ');

        self::assertSame('Salon Nima', $brand->name);
        self::assertSame('https://example.test/logo.png', $brand->logoUrl);
        self::assertSame('#3858e9', $brand->color);
    }

    public function testEveryPartMayBeEmpty(): void
    {
        $brand = Brand::of('', '', '');

        self::assertSame('Product', $brand->displayName('Product'));
        self::assertSame('', $brand->logoUrl);
    }

    public function testAKeptNameReplacesTheProductName(): void
    {
        self::assertSame('نوبت‌یار من', Brand::of('نوبت‌یار من', '', '')->displayName('Product'));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'name too long' => [\str_repeat('a', 61), '', '', 'invalid_brand_name'];
        yield 'name with markup' => ['<b>x</b>', '', '', 'invalid_brand_name'];
        yield 'logo without a scheme' => ['', 'example.test/logo.png', '', 'invalid_brand_logo'];
        yield 'logo with javascript' => ['', 'javascript:alert(1)', '', 'invalid_brand_logo'];
        yield 'logo with a quote' => ['', 'https://example.test/a"b.png', '', 'invalid_brand_logo'];
        yield 'colour too short' => ['', '', '#fff', 'invalid_brand_color'];
        yield 'colour without a hash' => ['', '', '3858e9', 'invalid_brand_color'];
        yield 'colour by name' => ['', '', 'red', 'invalid_brand_color'];
    }

    /**
     * @dataProvider invalid
     */
    public function testRejectsWhatItCannotStoreSafely(string $name, string $logo, string $color, string $code): void
    {
        try {
            Brand::of($name, $logo, $color);
            self::fail('Expected InvalidValue.');
        } catch (InvalidValue $e) {
            self::assertSame($code, $e->errorCode);
        }
    }

    public function testAHandEditedOptionFallsBackPartByPart(): void
    {
        $brand = Brand::fromStored(['name' => 'Kept', 'logo_url' => 'javascript:x', 'color' => 'blue']);

        self::assertSame('Kept', $brand->name);
        self::assertSame('', $brand->logoUrl);
        self::assertSame('', $brand->color);
    }

    public function testANonStringOptionValueIsIgnored(): void
    {
        self::assertSame('', Brand::fromStored(['name' => 5, 'color' => ['#fff']])->name);
    }
}
