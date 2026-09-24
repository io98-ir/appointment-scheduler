<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * A phone number in E.164 form (+989121234567).
 *
 * Input is what people type or paste: Persian or Arabic-Indic digits, spaces,
 * dashes, brackets, bidi marks from RTL text. A number without a country code
 * is read as Iranian; other countries must be given with + or 00.
 */
final class PhoneNumber
{
    private const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /** Whitespace, separators, and invisible format characters (bidi marks, ZWNJ, …). */
    private const NOISE = '/[\s\x{00A0}().\-\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{FEFF}]/u';

    private function __construct(public readonly string $e164)
    {
    }

    public static function fromInput(string $input): self
    {
        $compact = \preg_replace(self::NOISE, '', \strtr($input, self::DIGITS));
        if (null === $compact) {
            throw self::invalid(); // Not valid UTF-8.
        }
        if (\str_starts_with($compact, '00')) {
            $compact = '+' . \substr($compact, 2);
        }
        // The written form "+98 (0) 912…": the national trunk 0 after the country code.
        if (1 === \preg_match('/^\+980[1-9]\d{9}$/D', $compact)) {
            $compact = '+98' . \substr($compact, 4);
        }

        // An Iranian national number is 10 digits and never starts with 0.
        $e164 = match (true) {
            1 === \preg_match('/^\+98[1-9]\d{9}$/D', $compact) => $compact,
            1 === \preg_match('/^\+98/', $compact) => null,
            1 === \preg_match('/^\+[1-9]\d{7,14}$/D', $compact) => $compact,
            1 === \preg_match('/^0[1-9]\d{9}$/D', $compact) => '+98' . \substr($compact, 1),
            1 === \preg_match('/^98[1-9]\d{9}$/D', $compact) => '+' . $compact,
            1 === \preg_match('/^9\d{9}$/D', $compact) => '+98' . $compact,
            default => null,
        };
        if (null === $e164) {
            throw self::invalid();
        }

        return new self($e164);
    }

    public function equals(self $other): bool
    {
        return $this->e164 === $other->e164;
    }

    public function __toString(): string
    {
        return $this->e164;
    }

    private static function invalid(): InvalidValue
    {
        return new InvalidValue('invalid_phone', 'Not a valid phone number.');
    }
}
