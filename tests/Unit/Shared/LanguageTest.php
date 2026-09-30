<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\Language;

final class LanguageTest extends TestCase
{
    /**
     * @return iterable<string, array{Language, string, string, string}>
     */
    public static function cases(): iterable
    {
        yield 'persian ignores an English site' => [Language::Persian, 'en_US', 'fa_IR', 'rtl'];
        yield 'english ignores a Persian site' => [Language::English, 'fa_IR', 'en_US', 'ltr'];
        yield 'auto follows a Persian site' => [Language::Auto, 'fa_IR', 'fa_IR', 'rtl'];
        yield 'auto follows a short Persian locale' => [Language::Auto, 'fa', 'fa_IR', 'rtl'];
        yield 'auto on an English site' => [Language::Auto, 'en_GB', 'en_US', 'ltr'];
        yield 'auto on a language without a translation reads English' => [Language::Auto, 'de_DE', 'en_US', 'ltr'];
    }

    /**
     * @dataProvider cases
     */
    public function testLocaleAndDirection(Language $language, string $site, string $locale, string $direction): void
    {
        self::assertSame($locale, $language->locale($site));
        self::assertSame($direction, $language->direction($site));
    }
}
