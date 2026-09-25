<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Log;

/**
 * Masks personal data in text bound for the log, which is not encrypted and
 * is shown to admins (principles §7).
 *
 * - An email keeps its domain: a mail provider's problem shows up by domain.
 * - A run of 8 or more digits (phone, card, national id; Persian and Arabic
 *   digits too, spaces, dashes or parentheses allowed between them) keeps
 *   its last 4.
 *   An ISO date (2026-09-25) is left alone; a Unix timestamp is masked too.
 */
final class Pii
{
    private const EMAIL = '/[^\s@<>()\[\]{}"\',;:]+@([^\s@<>()\[\]{}"\',;:]+)/u';

    private const DIGIT_RUN = '/(?<!\p{Nd})'
        // Not where an ISO date starts.
        . '(?!\p{Nd}{4}-\p{Nd}{2}-\p{Nd}{2}(?!\p{Nd}))'
        . '[+(]?\p{Nd}(?:[ \-()]{0,2}\p{Nd}){7,}/u';

    public static function mask(string $text): string
    {
        $text = \preg_replace(self::EMAIL, '***@$1', $text);
        if (null === $text) {
            // Invalid UTF-8: nothing safe to keep.
            return '[unreadable text]';
        }

        return \preg_replace_callback(
            self::DIGIT_RUN,
            static function (array $match): string {
                \preg_match_all('/\p{Nd}/u', $match[0], $digits);

                return '***' . \implode('', \array_slice($digits[0], -4));
            },
            $text
        ) ?? '[unreadable text]';
    }
}
