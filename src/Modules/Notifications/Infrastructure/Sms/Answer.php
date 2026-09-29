<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Sms;

/**
 * Reads a decoded provider answer without trusting its shape.
 */
final class Answer
{
    /**
     * @param array<mixed> $answer
     */
    public static function at(array $answer, string|int ...$path): mixed
    {
        $value = $answer;
        foreach ($path as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @return string the value when it is a string or a number, else empty.
     */
    public static function text(mixed $value): string
    {
        return \is_string($value) || \is_int($value) ? (string) $value : '';
    }

    /**
     * A value for a provider that takes a single line without separators.
     */
    public static function oneLine(string $value, string $also = ''): string
    {
        $clean = \preg_replace('/\s*[\r\n]+\s*/', ' ', $value) ?? '';

        return '' === $also ? $clean : \str_replace($also, ' ', $clean);
    }
}
