<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\Email;
use Vaqtyar\Shared\Domain\InvalidValue;

final class EmailTest extends TestCase
{
    public function testTrimsAndLowercasesTheDomainOnly(): void
    {
        // The local part is case-sensitive by the standard; the domain is not.
        self::assertSame('Sara.K@example.com', Email::fromInput('  Sara.K@EXAMPLE.Com ')->value);
        self::assertSame('Sara.K@example.com', (string) Email::fromInput('Sara.K@EXAMPLE.Com'));
    }

    public function testEqualityIgnoresTheCaseOfTheLocalPart(): void
    {
        // Real mail servers treat it case-insensitively, and so does customer matching.
        self::assertTrue(Email::fromInput('Sara@example.com')->equals(Email::fromInput('sara@EXAMPLE.com')));
        self::assertFalse(Email::fromInput('sara@example.com')->equals(Email::fromInput('sara@example.org')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'empty' => [''];
        yield 'no at sign' => ['sara.example.com'];
        yield 'no domain' => ['sara@'];
        yield 'space inside' => ['sa ra@example.com'];
        yield 'two at signs' => ['sara@@example.com'];
        yield 'newline (header injection)' => ["sara@example.com\nBcc: x@example.com"];
        yield 'too long' => [\str_repeat('a', 250) . '@example.com'];
    }

    /**
     * @dataProvider invalidInputs
     */
    public function testRejectsInvalidAddresses(string $input): void
    {
        try {
            Email::fromInput($input);
            self::fail('Accepted ' . $input);
        } catch (InvalidValue $e) {
            self::assertSame('invalid_email', $e->errorCode);
        }
    }
}
