<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Domain\Pricing;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Domain\Pricing\ChosenExtra;
use Vaqtyar\Modules\Booking\Domain\Pricing\Coupon;
use Vaqtyar\Modules\Booking\Domain\Pricing\CouponType;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceCalculator;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceContext;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceLine;
use Vaqtyar\Modules\Booking\Domain\Pricing\TimeRule;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Rounding;

/**
 * The steps of booking-engine §5, as a table. The start is Friday
 * 2026-10-02 18:00 in Tehran unless a case says otherwise; the base price
 * is 1,000,000 rials.
 */
final class PriceCalculatorTest extends TestCase
{
    private const SERVICE = 7;
    private const NOW = '2026-10-01 12:00';

    /**
     * @return iterable<string, array{array<string, mixed>, list<array{string, int}>, int}>
     */
    public static function cases(): iterable
    {
        yield 'base only' => [[], [[PriceLine::BASE, 1_000_000]], 1_000_000];

        yield 'staff price is the base' => [
            ['base' => 1_200_000],
            [[PriceLine::BASE, 1_200_000]],
            1_200_000,
        ];

        yield 'a Friday-evening rule adds 20%' => [
            ['timeRules' => [self::rule(1, [6], '17:00', '22:00', 20)]],
            [[PriceLine::BASE, 1_000_000], [PriceLine::TIME_RULE, 200_000]],
            1_200_000,
        ];

        yield 'a morning discount does not match an evening start' => [
            ['timeRules' => [self::rule(1, [], '08:00', '12:00', -10)]],
            [[PriceLine::BASE, 1_000_000]],
            1_000_000,
        ];

        yield 'only the first matching rule applies' => [
            ['timeRules' => [self::rule(1, [], '00:00', '24:00', -10), self::rule(2, [6], '17:00', '22:00', 20)]],
            [[PriceLine::BASE, 1_000_000], [PriceLine::TIME_RULE, -100_000]],
            900_000,
        ];

        yield 'a rule outside its dates does not match' => [
            ['timeRules' => [self::rule(1, [], '00:00', '24:00', 50, '2026-10-03', null)]],
            [[PriceLine::BASE, 1_000_000]],
            1_000_000,
        ];

        yield 'the time range end is exclusive' => [
            ['timeRules' => [self::rule(1, [], '12:00', '18:00', 50)]],
            [[PriceLine::BASE, 1_000_000]],
            1_000_000,
        ];

        yield 'extras, a line each' => [
            [
                'extras' => [
                    new ChosenExtra(40, Money::ofRial(150_000), 2),
                    new ChosenExtra(41, Money::ofRial(90_000), 1),
                ],
            ],
            [[PriceLine::BASE, 1_000_000], [PriceLine::EXTRA, 300_000], [PriceLine::EXTRA, 90_000]],
            1_390_000,
        ];

        yield 'a party of three pays three times, extras included' => [
            ['partySize' => 3, 'extras' => [new ChosenExtra(40, Money::ofRial(100_000), 1)]],
            [[PriceLine::BASE, 1_000_000], [PriceLine::EXTRA, 100_000], [PriceLine::PARTY, 2_200_000]],
            3_300_000,
        ];

        yield 'a percent coupon on the whole total' => [
            ['partySize' => 2, 'coupon' => self::coupon(CouponType::Percent, 15)],
            [[PriceLine::BASE, 1_000_000], [PriceLine::PARTY, 1_000_000], [PriceLine::COUPON, -300_000]],
            1_700_000,
        ];

        yield 'a percent coupon rounds its discount up, for the customer' => [
            ['base' => 999, 'coupon' => self::coupon(CouponType::Percent, 10)],
            [[PriceLine::BASE, 999], [PriceLine::COUPON, -100]],
            899,
        ];

        yield 'a fixed coupon never takes the price below zero' => [
            ['base' => 300_000, 'coupon' => self::coupon(CouponType::Fixed, 500_000)],
            [[PriceLine::BASE, 300_000], [PriceLine::COUPON, -300_000]],
            0,
        ];

        yield 'rounding to whole thousand tomans, half up' => [
            ['base' => 1_234_567, 'step' => 10_000, 'rounding' => Rounding::HalfUp],
            [[PriceLine::BASE, 1_234_567], [PriceLine::ROUNDING, -4_567]],
            1_230_000,
        ];

        yield 'rounding up' => [
            ['base' => 1_230_001, 'step' => 10_000, 'rounding' => Rounding::Up],
            [[PriceLine::BASE, 1_230_001], [PriceLine::ROUNDING, 9_999]],
            1_240_000,
        ];

        yield 'rounding down' => [
            ['base' => 1_239_999, 'step' => 10_000, 'rounding' => Rounding::Down],
            [[PriceLine::BASE, 1_239_999], [PriceLine::ROUNDING, -9_999]],
            1_230_000,
        ];

        yield 'every step in order' => [
            [
                'timeRules' => [self::rule(9, [6], '17:00', '22:00', 20)],
                'extras' => [new ChosenExtra(40, Money::ofRial(150_000), 1)],
                'partySize' => 2,
                'coupon' => self::coupon(CouponType::Percent, 10),
                'step' => 10_000,
            ],
            [
                [PriceLine::BASE, 1_000_000],
                [PriceLine::TIME_RULE, 200_000],
                [PriceLine::EXTRA, 150_000],
                [PriceLine::PARTY, 1_350_000],
                [PriceLine::COUPON, -270_000],
            ],
            2_430_000,
        ];
    }

    /**
     * @dataProvider cases
     * @param array<string, mixed> $given
     * @param list<array{string, int}> $lines
     */
    public function testQuote(array $given, array $lines, int $total): void
    {
        /** @var list<TimeRule> $timeRules */
        $timeRules = $given['timeRules'] ?? [];
        $step = $given['step'] ?? 1;
        $rounding = $given['rounding'] ?? Rounding::HalfUp;
        self::assertIsInt($step);
        self::assertInstanceOf(Rounding::class, $rounding);

        $quote = PriceCalculator::standard($timeRules, $step, $rounding)->quote(self::context($given));

        self::assertSame(
            $lines,
            \array_map(static fn (PriceLine $line): array => [$line->code, $line->amount->amount], $quote->lines)
        );
        self::assertSame($total, $quote->total()->amount);
    }

    /**
     * @return iterable<string, array{Coupon, string}>
     */
    public static function unusableCoupons(): iterable
    {
        yield 'inactive' => [self::coupon(CouponType::Fixed, 1000, active: false), 'coupon_inactive'];
        yield 'not yet valid' => [
            self::coupon(CouponType::Fixed, 1000, validFrom: self::utc('2026-10-02 00:00')),
            'coupon_expired',
        ];
        yield 'expired' => [
            self::coupon(CouponType::Fixed, 1000, validTo: self::utc(self::NOW)),
            'coupon_expired',
        ];
        yield 'another service' => [self::coupon(CouponType::Fixed, 1000, serviceIds: [8]), 'coupon_not_applicable'];
        yield 'used up' => [self::coupon(CouponType::Fixed, 1000, maxUses: 5, used: 5), 'coupon_used_up'];
    }

    /**
     * @dataProvider unusableCoupons
     */
    public function testAnUnusableCouponIsRefusedWithItsReason(Coupon $coupon, string $code): void
    {
        try {
            PriceCalculator::standard([], 1, Rounding::HalfUp)->quote(self::context(['coupon' => $coupon]));
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame($code, $e->errorCode);
        }
    }

    public function testTheQuoteSerializesForTheSnapshot(): void
    {
        $quote = PriceCalculator::standard([], 1, Rounding::HalfUp)->quote(self::context([
            'extras' => [new ChosenExtra(40, Money::ofRial(150_000), 2)],
        ]));

        self::assertSame(
            [
                'total' => self::irr(1_300_000),
                'lines' => [
                    ['code' => 'base', 'amount' => self::irr(1_000_000), 'ref' => null, 'qty' => 1],
                    ['code' => 'extra', 'amount' => self::irr(300_000), 'ref' => 40, 'qty' => 2],
                ],
            ],
            $quote->toArray()
        );
    }

    /**
     * @return iterable<string, array{\Closure(): mixed}>
     */
    public static function invalidRules(): iterable
    {
        yield 'weekday 7' => [static fn () => self::rule(1, [7], '08:00', '12:00', 10)];
        yield 'empty time range' => [static fn () => self::rule(1, [], '12:00', '12:00', 10)];
        yield 'zero percent' => [static fn () => self::rule(1, [], '08:00', '12:00', 0)];
        yield 'below -100%' => [static fn () => self::rule(1, [], '08:00', '12:00', -101)];
        yield 'dates reversed' => [static fn () => self::rule(1, [], '08:00', '12:00', 10, '2026-10-05', '2026-10-01')];
        yield 'percent coupon over 100' => [static fn () => self::coupon(CouponType::Percent, 101)];
    }

    /**
     * @dataProvider invalidRules
     * @param \Closure(): mixed $make
     */
    public function testInvalidRulesAreRefused(\Closure $make): void
    {
        $this->expectException(InvalidValue::class);
        $make();
    }

    /**
     * @param array<string, mixed> $given
     */
    private static function context(array $given): PriceContext
    {
        $base = $given['base'] ?? 1_000_000;
        $partySize = $given['partySize'] ?? 1;
        /** @var list<ChosenExtra> $extras */
        $extras = $given['extras'] ?? [];
        $coupon = $given['coupon'] ?? null;
        self::assertIsInt($base);
        self::assertIsInt($partySize);
        self::assertTrue(null === $coupon || $coupon instanceof Coupon);

        return new PriceContext(
            self::SERVICE,
            Money::ofRial($base),
            self::utc('2026-10-02 18:00'),
            new \DateTimeZone('Asia/Tehran'),
            $extras,
            $partySize,
            self::utc(self::NOW),
            $coupon
        );
    }

    /**
     * @param list<int> $weekdays
     */
    private static function rule(
        int $id,
        array $weekdays,
        string $from,
        string $to,
        int $percent,
        ?string $validFrom = null,
        ?string $validTo = null,
    ): TimeRule {
        $minutes = static fn (string $time): int => (int) \substr($time, 0, 2) * 60 + (int) \substr($time, 3, 2);

        return new TimeRule(
            $id,
            $weekdays,
            $minutes($from),
            $minutes($to),
            null === $validFrom ? null : LocalDate::fromString($validFrom),
            null === $validTo ? null : LocalDate::fromString($validTo),
            $percent
        );
    }

    /**
     * @param ?list<int> $serviceIds
     */
    private static function coupon(
        CouponType $type,
        int $value,
        bool $active = true,
        ?int $validFrom = null,
        ?int $validTo = null,
        ?int $maxUses = null,
        int $used = 0,
        ?array $serviceIds = null,
    ): Coupon {
        return new Coupon(3, 'NOWRUZ', $type, $value, $active, $validFrom, $validTo, $maxUses, $used, $serviceIds);
    }

    /**
     * @return array{amount: int, currency: string}
     */
    private static function irr(int $amount): array
    {
        return ['amount' => $amount, 'currency' => 'IRR'];
    }

    /**
     * @param string $tehran "Y-m-d H:i" in Tehran.
     */
    private static function utc(string $tehran): int
    {
        return (new \DateTimeImmutable($tehran, new \DateTimeZone('Asia/Tehran')))->getTimestamp();
    }
}
