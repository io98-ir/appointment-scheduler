<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Domain\Hold;
use Vaqtyar\Modules\Booking\Domain\HoldToken;
use Vaqtyar\Modules\Booking\Domain\LockKey;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Shared\Domain\InvalidValue;

final class HoldTest extends TestCase
{
    private const T = 1_800_000_000;

    public function testItLocksItsStaffMemberAndUnitsInSortedOrder(): void
    {
        self::assertSame(['res:10', 'res:9', 'staff:3'], self::hold(resourceIds: [9, 10])->lockKeys());
        self::assertSame(
            ['res:2', 'staff:1', 'staff:12'],
            LockKey::sorted([12, 1, 12], [2, 2]),
            'Unique, and sorted as strings, the order every writer uses.'
        );
    }

    /**
     * @return iterable<string, array{array<string, int>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'end before start' => [['end' => self::T - 60], 'invalid_interval'];
        yield 'start before the buffer' => [['from' => self::T + 60], 'invalid_interval'];
        yield 'end after the buffer' => [['to' => self::T + 1800], 'invalid_interval'];
        yield 'longer than seven days' => [['to' => self::T - 60 + 7 * 86_400 + 1], 'booking_too_long'];
        yield 'no party' => [['partySize' => 0], 'invalid_party_size'];
        yield 'already expired' => [['expiresAt' => self::T - 3600], 'invalid_expiry'];
        yield 'past its lifetime' => [['expiresAt' => self::T - 3600 + 1201], 'invalid_expiry'];
    }

    /**
     * @dataProvider invalid
     * @param array<string, int> $change
     */
    public function testInvalidHoldsAreRefused(array $change, string $code): void
    {
        try {
            self::hold(...$change);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame($code, $e->errorCode);
        }
    }

    public function testAnExtensionIsAFullTtlButNotPastTheLifetime(): void
    {
        self::assertSame(
            [self::T + 300 + 600, self::T + 1200],
            [Hold::extendedExpiry(self::T, self::T + 300), Hold::extendedExpiry(self::T, self::T + 900)]
        );
    }

    public function testATokenIsRandomAndOnlyItsHashIsKept(): void
    {
        $token = HoldToken::generate();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token->value);
        self::assertNotSame($token->value, HoldToken::generate()->value);
        self::assertSame(\hash('sha256', $token->value), HoldToken::fromString($token->value)->hash());
        $this->expectException(InvalidValue::class);
        HoldToken::fromString($token->value . '=');
    }

    /**
     * @param list<int> $resourceIds
     */
    private static function hold(
        int $end = self::T + 3600,
        int $from = self::T - 60,
        int $to = self::T + 3660,
        int $partySize = 1,
        int $expiresAt = self::T - 3600 + 600,
        array $resourceIds = [],
    ): Hold {
        return new Hold(
            1,
            5,
            3,
            self::T,
            $end,
            $from,
            $to,
            $partySize,
            [],
            $resourceIds,
            self::T - 3600,
            $expiresAt,
            PriceQuote::empty()
        );
    }
}
