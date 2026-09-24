<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\Currency;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Rounding;

final class MoneyTest extends TestCase
{
    public function testIsAnIntegerAmountOfRial(): void
    {
        $price = Money::ofRial(1_500_000);

        self::assertSame(1_500_000, $price->amount);
        self::assertSame(Currency::IRR, $price->currency);
        self::assertSame(['amount' => 1_500_000, 'currency' => 'IRR'], $price->toArray());
    }

    public function testAddSubtractAndMultiplyKeepTheCurrency(): void
    {
        $a = Money::ofRial(1_000);
        $b = Money::ofRial(300);

        self::assertTrue($a->add($b)->equals(Money::ofRial(1_300)));
        self::assertTrue($a->subtract($b)->equals(Money::ofRial(700)));
        self::assertTrue($b->subtract($a)->equals(Money::ofRial(-700)));
        self::assertTrue($b->multiply(3)->equals(Money::ofRial(900)));
        self::assertTrue(Money::zero()->equals(Money::ofRial(0)));
    }

    public function testComparesAmountsAndSigns(): void
    {
        $a = Money::ofRial(1_000);

        self::assertTrue($a->isGreaterThan(Money::ofRial(999)));
        self::assertFalse($a->isGreaterThan(Money::ofRial(1_000)));
        self::assertTrue($a->isLessThan(Money::ofRial(1_001)));
        self::assertTrue(Money::zero()->isZero());
        self::assertTrue(Money::ofRial(-1)->isNegative());
        self::assertFalse(Money::zero()->isNegative());
    }

    /**
     * @return iterable<string, array{int, int, Rounding, int}>
     */
    public static function percentages(): iterable
    {
        yield 'exact' => [1_000_000, 30, Rounding::HalfUp, 300_000];
        yield 'half up rounds .5 away from zero' => [1_005, 50, Rounding::HalfUp, 503];
        yield 'half up rounds below .5 down' => [1_001, 30, Rounding::HalfUp, 300];
        yield 'down truncates' => [1_009, 50, Rounding::Down, 504];
        yield 'up rounds any remainder up' => [1_001, 50, Rounding::Up, 501];
        yield 'negative half up' => [-1_005, 50, Rounding::HalfUp, -503];
        yield 'negative down goes toward zero' => [-1_009, 50, Rounding::Down, -504];
        yield 'negative up goes away from zero' => [-1_001, 50, Rounding::Up, -501];
        yield 'zero percent' => [1_000, 0, Rounding::HalfUp, 0];
        yield 'more than 100 percent' => [1_000, 150, Rounding::HalfUp, 1_500];
    }

    /**
     * @dataProvider percentages
     */
    public function testPercentRoundsExplicitly(int $amount, int $percent, Rounding $rounding, int $expected): void
    {
        self::assertSame($expected, Money::ofRial($amount)->percent($percent, $rounding)->amount);
    }

    public function testRejectsANegativePercentage(): void
    {
        $this->expectException(InvalidValue::class);

        Money::ofRial(1_000)->percent(-10, Rounding::HalfUp);
    }

    public function testOverflowFailsInsteadOfBecomingAFloat(): void
    {
        $this->expectException(InvalidValue::class);

        Money::ofRial(\PHP_INT_MAX)->add(Money::ofRial(1));
    }

    public function testMultiplyOverflowFails(): void
    {
        $this->expectException(InvalidValue::class);

        Money::ofRial(\intdiv(\PHP_INT_MAX, 2) + 1)->multiply(2);
    }

    public function testPercentOfAHugeAmountFailsInsteadOfOverflowing(): void
    {
        $this->expectException(InvalidValue::class);

        Money::ofRial(\PHP_INT_MAX)->percent(50, Rounding::HalfUp);
    }

    public function testSubtractOverflowFails(): void
    {
        $this->expectException(InvalidValue::class);

        Money::ofRial(\PHP_INT_MIN)->subtract(Money::ofRial(1));
    }

    public function testFromArrayRoundTrips(): void
    {
        self::assertTrue(Money::fromArray(['amount' => 42, 'currency' => 'IRR'])->equals(Money::ofRial(42)));
    }

    public function testFromArrayRejectsUnknownCurrencyAndNonIntegerAmounts(): void
    {
        $invalid = [['amount' => 42, 'currency' => 'USD'], ['amount' => 4.2, 'currency' => 'IRR'], ['amount' => '42']];
        foreach ($invalid as $data) {
            try {
                Money::fromArray($data);
                self::fail('Accepted ' . \json_encode($data));
            } catch (InvalidValue $e) {
                self::assertSame('invalid_money', $e->errorCode);
            }
        }
    }
}
