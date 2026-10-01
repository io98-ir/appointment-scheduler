<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Domain\Policy;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Domain\Policy\ApprovalPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\BookingTerms;
use Vaqtyar\Modules\Booking\Domain\Policy\BookingWindowPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\DepositPolicy;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;

final class TermsPoliciesTest extends TestCase
{
    /**
     * @return iterable<string, array{DepositPolicy, int, int}> policy, price in rials, charged now.
     */
    public static function charges(): iterable
    {
        yield 'no deposit asks for everything' => [DepositPolicy::lenient(), 3_000_000, 3_000_000];
        yield 'a percent' => [new DepositPolicy('percent', 30, false), 3_000_000, 900_000];
        yield 'a percent rounds half up' => [new DepositPolicy('percent', 15, false), 1_005, 151];
        yield 'a tiny percent is never nothing' => [new DepositPolicy('percent', 1, false), 10, 1];
        yield 'a hundred percent is the whole price' => [new DepositPolicy('percent', 100, true), 2_500_000, 2_500_000];
        yield 'a fixed amount' => [new DepositPolicy('fixed', 500_000, true), 3_000_000, 500_000];
        yield 'a fixed amount is capped at the price' => [
            new DepositPolicy('fixed', 5_000_000, false),
            3_000_000,
            3_000_000,
        ];
        yield 'a free booking asks for nothing' => [new DepositPolicy('percent', 30, true), 0, 0];
    }

    /**
     * @dataProvider charges
     */
    public function testWhatIsChargedOnline(DepositPolicy $policy, int $price, int $due): void
    {
        self::assertSame($due, $policy->dueNow(Money::ofRial($price))->amount);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function badDeposits(): iterable
    {
        yield 'an unknown kind' => ['gift', 10];
        yield 'zero percent' => ['percent', 0];
        yield 'over a hundred percent' => ['percent', 101];
        yield 'a zero fixed amount' => ['fixed', 0];
        yield 'a negative fixed amount' => ['fixed', -5];
    }

    /**
     * @dataProvider badDeposits
     */
    public function testRejectsADepositThatMakesNoSense(string $kind, int $value): void
    {
        $this->expectException(InvalidValue::class);

        new DepositPolicy($kind, $value, false);
    }

    public function testDepositConfigRoundTripsAndIgnoresTheValueOfNone(): void
    {
        $policy = DepositPolicy::fromConfig(['kind' => 'percent', 'value' => 30, 'required' => true]);

        self::assertSame(['kind' => 'percent', 'value' => 30, 'required' => true], $policy->toConfig());
        self::assertTrue($policy->isDeposit());
        self::assertSame(
            ['kind' => 'none', 'value' => 0, 'required' => true],
            DepositPolicy::fromConfig(['kind' => 'none', 'value' => 77, 'required' => true])->toConfig()
        );
        self::assertFalse(DepositPolicy::fromConfig([])->required);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function brokenConfigs(): iterable
    {
        yield 'a text value' => [['kind' => 'percent', 'value' => '30']];
        yield 'a number as the kind' => [['kind' => 5]];
        yield 'required as text' => [['required' => 'yes']];
    }

    /**
     * @dataProvider brokenConfigs
     * @param array<mixed> $config
     */
    public function testBrokenDepositConfigIsRejected(array $config): void
    {
        $this->expectException(InvalidValue::class);

        DepositPolicy::fromConfig($config);
    }

    public function testApprovalConfig(): void
    {
        self::assertTrue(ApprovalPolicy::fromConfig(['required' => true])->required);
        self::assertFalse(ApprovalPolicy::fromConfig([])->required);
        self::assertSame(['required' => true], (new ApprovalPolicy(true))->toConfig());

        $this->expectException(InvalidValue::class);
        ApprovalPolicy::fromConfig(['required' => 1]);
    }

    public function testBookingWindowKeepsWhatIsLeftOutAsNull(): void
    {
        $window = BookingWindowPolicy::fromConfig(['min_notice_min' => 120]);

        self::assertSame(['min_notice_min' => 120, 'max_advance_days' => null], $window->toConfig());
        self::assertSame(
            [0, 1],
            [(new BookingWindowPolicy(0, 1))->minNoticeMin, (new BookingWindowPolicy(0, 1))->maxAdvanceDays]
        );
    }

    /**
     * @return iterable<string, array{?int, ?int}>
     */
    public static function badWindows(): iterable
    {
        yield 'a negative notice' => [-1, null];
        yield 'a notice over a year' => [365 * 1440 + 1, null];
        yield 'no days ahead' => [null, 0];
        yield 'over two years ahead' => [null, 731];
    }

    /**
     * @dataProvider badWindows
     */
    public function testBookingWindowBounds(?int $notice, ?int $advance): void
    {
        $this->expectException(InvalidValue::class);

        new BookingWindowPolicy($notice, $advance);
    }

    public function testTheLenientTermsAskForNothing(): void
    {
        $terms = BookingTerms::lenient();

        self::assertFalse($terms->deposit->required);
        self::assertFalse($terms->deposit->isDeposit());
        self::assertFalse($terms->approval->required);
        self::assertNull($terms->window->minNoticeMin);
    }
}
