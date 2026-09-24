<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Ulid;

final class UlidTest extends TestCase
{
    public function testEncodesTimeAndRandomnessInCrockfordBase32(): void
    {
        // The spec's example ULID, split into its parts with an independent decoder.
        $ulid = Ulid::fromParts(new DateTimeImmutable('@1469922850.259'), (string) \hex2bin('d6764c61efb99302bd5b'));

        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $ulid->toString());
        self::assertSame(
            '01ARZ3NDEK' . \str_repeat('0', 16),
            Ulid::fromParts(new DateTimeImmutable('@1469922850.259'), \str_repeat("\0", 10))->toString()
        );
    }

    public function testEncodesTheMaximumValue(): void
    {
        $ulid = Ulid::fromParts(new DateTimeImmutable('@281474976710.655'), \str_repeat("\xFF", 10));

        self::assertSame('7ZZZZZZZZZZZZZZZZZZZZZZZZZ', $ulid->toString());
    }

    public function testLaterTimesSortLater(): void
    {
        $random = \str_repeat("\x7F", 10);
        $earlier = Ulid::fromParts(new DateTimeImmutable('2026-09-24T10:00:00.000Z'), $random)->toString();
        $later = Ulid::fromParts(new DateTimeImmutable('2026-09-24T10:00:00.001Z'), $random)->toString();

        self::assertLessThan(0, \strcmp($earlier, $later));
    }

    public function testParsesCaseInsensitivelyAndReturnsCanonicalForm(): void
    {
        $ulid = Ulid::fromString('01arz3ndektsv4rrffq69g5fav');

        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $ulid->toString());
        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', (string) $ulid);
        self::assertTrue($ulid->equals(Ulid::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUlids(): iterable
    {
        yield 'too short' => ['01ARZ3NDEKTSV4RRFFQ69G5FA'];
        yield 'too long' => ['01ARZ3NDEKTSV4RRFFQ69G5FAVX'];
        yield 'excluded letter I' => ['01ARZ3NDEKTSV4RRFFQ69G5FAI'];
        yield 'excluded letter U' => ['01ARZ3NDEKTSV4RRFFQ69G5FAU'];
        yield 'overflows 128 bits' => ['81ARZ3NDEKTSV4RRFFQ69G5FAV'];
        yield 'trailing newline' => ["01ARZ3NDEKTSV4RRFFQ69G5FAV\n"];
    }

    /**
     * @dataProvider invalidUlids
     */
    public function testRejectsInvalidStrings(string $input): void
    {
        $this->expectException(InvalidValue::class);

        Ulid::fromString($input);
    }

    public function testRejectsRandomnessOfTheWrongLength(): void
    {
        $this->expectException(InvalidValue::class);

        Ulid::fromParts(new DateTimeImmutable('2026-09-24T10:00:00Z'), 'short');
    }

    public function testRejectsTimesBeforeTheEpoch(): void
    {
        $this->expectException(InvalidValue::class);

        Ulid::fromParts(new DateTimeImmutable('1969-12-31T23:59:59Z'), \str_repeat("\0", 10));
    }
}
