<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * Text as a search compares it: Arabic ya and kaf as Persian, no diacritics
 * or tatweel, a zero-width non-joiner as a space, Latin digits, lowercase,
 * single spaces. Stored names (search_name columns) and search queries go
 * through the same function, so "علي" finds "علی".
 */
final class SearchText
{
    public static function normalize(string $text): string
    {
        $text = \strtr($text, [
            "\u{064A}" => "\u{06CC}",
            "\u{0649}" => "\u{06CC}",
            "\u{0643}" => "\u{06A9}",
            "\u{200C}" => ' ',
            "\u{0640}" => '',
        ]);
        $text = (string) \preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);

        return \trim((string) \preg_replace('/\s+/u', ' ', \mb_strtolower(PersianDigits::toLatin($text))));
    }
}
