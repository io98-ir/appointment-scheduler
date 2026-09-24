<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\PhoneNumber;

final class PhoneNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function iranianInputs(): iterable
    {
        yield 'national mobile' => ['09121234567', '+989121234567'];
        yield 'without leading zero' => ['9121234567', '+989121234567'];
        yield 'E.164' => ['+989121234567', '+989121234567'];
        yield '00 international prefix' => ['00989121234567', '+989121234567'];
        yield 'country code without plus' => ['989121234567', '+989121234567'];
        yield 'Persian digits' => ['۰۹۱۲۱۲۳۴۵۶۷', '+989121234567'];
        yield 'Arabic-Indic digits' => ['٠٩١٢١٢٣٤٥٦٧', '+989121234567'];
        yield 'mixed digits' => ['۰912۱۲۳4567', '+989121234567'];
        yield 'spaces, dashes, dots and brackets' => ['(0912) 123-45.67', '+989121234567'];
        yield 'bidi marks from a copied RTL text' => ["\u{200F}۰۹۱۲ ۱۲۳ ۴۵۶۷\u{200E}", '+989121234567'];
        yield 'Persian plus-prefixed' => ['+۹۸۹۱۲۱۲۳۴۵۶۷', '+989121234567'];
        yield 'Tehran landline' => ['021-88776655', '+982188776655'];
        yield 'surrounding whitespace and non-breaking space' => [" 0912\u{00A0}123 4567 ", '+989121234567'];
        yield 'trunk zero after the country code' => ['+98 (0) 912 123 4567', '+989121234567'];
    }

    /**
     * @dataProvider iranianInputs
     */
    public function testNormalisesToE164(string $input, string $e164): void
    {
        self::assertSame($e164, PhoneNumber::fromInput($input)->e164);
    }

    public function testKeepsOtherCountriesWhenGivenInInternationalForm(): void
    {
        self::assertSame('+971501234567', PhoneNumber::fromInput('+971 50 123 4567')->e164);
        self::assertSame('+14155552671', PhoneNumber::fromInput('0014155552671')->e164);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'empty' => [''];
        yield 'letters' => ['0912abc4567'];
        yield 'too short' => ['0912123'];
        yield 'too long for Iran' => ['091212345678'];
        yield 'too long for E.164' => ['+1234567890123456'];
        yield 'plus in the middle' => ['0912+1234567'];
        yield 'zero country code' => ['+0123456789'];
        yield 'only a plus' => ['+'];
        yield 'invalid UTF-8' => ["0912\xC3\x281234567"];
        // A trunk 0 after +98 with a digit missing must not pass as a 10-digit number.
        yield 'trunk zero and a missing digit' => ['+98 0912 123 456'];
        yield 'same with 00 prefix' => ['0098 0912 123456'];
        yield 'country code without plus, national part starting with 0' => ['980912123456'];
        yield 'all zeros' => ['+980000000000'];
    }

    /**
     * @dataProvider invalidInputs
     */
    public function testRejectsWhatIsNotAPhoneNumber(string $input): void
    {
        try {
            PhoneNumber::fromInput($input);
            self::fail('Accepted ' . $input);
        } catch (InvalidValue $e) {
            self::assertSame('invalid_phone', $e->errorCode);
            if ('' !== $input) {
                self::assertStringNotContainsString($input, $e->getMessage(), 'No user input in messages.');
            }
        }
    }

    public function testNumbersEqualAfterNormalisation(): void
    {
        self::assertTrue(PhoneNumber::fromInput('09121234567')->equals(PhoneNumber::fromInput('+989121234567')));
        self::assertFalse(PhoneNumber::fromInput('09121234567')->equals(PhoneNumber::fromInput('09121234568')));
        self::assertSame('+989121234567', (string) PhoneNumber::fromInput('09121234567'));
    }
}
