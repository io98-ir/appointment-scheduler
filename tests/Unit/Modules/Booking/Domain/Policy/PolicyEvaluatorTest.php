<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Domain\Policy;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Domain\Policy\CancellationPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\Decision;
use Vaqtyar\Modules\Booking\Domain\Policy\PolicyEvaluator;
use Vaqtyar\Modules\Booking\Domain\Policy\RefundTier;
use Vaqtyar\Modules\Booking\Domain\Policy\ReschedulePolicy;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;

/**
 * The refund ladder and the deadlines of booking-engine §6: cancel up to
 * 24 hours before, 100% from 48 hours, 50% from 24; move up to 12 hours
 * before, twice at most.
 */
final class PolicyEvaluatorTest extends TestCase
{
    private const START = 1_800_000_000;

    private const HOUR = 3600;

    /**
     * @return iterable<string, array{int, bool, ?string, int, int}>
     */
    public static function ladder(): iterable
    {
        yield 'a week before' => [168 * self::HOUR, true, null, 100, 1_000_000];
        yield 'exactly 48 hours' => [48 * self::HOUR, true, null, 100, 1_000_000];
        yield 'a second under 48 hours' => [48 * self::HOUR - 1, true, null, 50, 500_000];
        yield 'exactly 24 hours' => [24 * self::HOUR, true, null, 50, 500_000];
        yield 'a second under 24 hours' => [24 * self::HOUR - 1, false, 'policy.cancel_window_passed', 0, 0];
        yield 'at the start' => [0, false, 'policy.already_started', 0, 0];
        yield 'after the start' => [-60, false, 'policy.already_started', 0, 0];
    }

    /**
     * @dataProvider ladder
     */
    public function testTheRefundLadder(int $before, bool $allowed, ?string $reason, int $percent, int $refund): void
    {
        $decision = self::evaluator()->cancel(self::START, self::START - $before, Money::ofRial(1_000_000));

        self::assertSame(
            [$allowed, $reason, $percent, $refund],
            [$decision->allowed, $decision->reasonCode, $decision->refundPercent, $decision->refund->amount]
        );
    }

    public function testARefundIsRoundedHalfUp(): void
    {
        $decision = self::evaluator()->cancel(self::START, self::START - 30 * self::HOUR, Money::ofRial(1_001));

        self::assertSame(501, $decision->refund->amount);
    }

    public function testNothingPaidRefundsNothingButTheShareIsShown(): void
    {
        $decision = self::evaluator()->cancel(self::START, self::START - 72 * self::HOUR, Money::zero());

        self::assertSame([true, 100, 0], [$decision->allowed, $decision->refundPercent, $decision->refund->amount]);
    }

    public function testBelowTheLowestTierNothingIsRefundedButCancellingIsAllowed(): void
    {
        $policy = new CancellationPolicy(null, [new RefundTier(24, 100)]);

        $decision = $policy->decide(self::START, self::START - self::HOUR, Money::ofRial(1_000));

        self::assertSame([true, 0], [$decision->allowed, $decision->refund->amount]);
    }

    public function testWithoutPoliciesEverythingIsAllowedAndRefunded(): void
    {
        $lenient = new PolicyEvaluator(CancellationPolicy::lenient(), ReschedulePolicy::lenient());

        self::assertEquals(
            Decision::allow(100, Money::ofRial(900)),
            $lenient->cancel(self::START, self::START - 60, Money::ofRial(900))
        );
        self::assertTrue($lenient->reschedule(self::START, self::START - 60, 50)->allowed);
    }

    /**
     * @return iterable<string, array{int, int, ?string}>
     */
    public static function moves(): iterable
    {
        yield 'first move, a day before' => [24 * self::HOUR, 0, null];
        yield 'second move, exactly 12 hours before' => [12 * self::HOUR, 1, null];
        yield 'a second under 12 hours' => [12 * self::HOUR - 1, 0, 'policy.reschedule_window_passed'];
        yield 'third move' => [24 * self::HOUR, 2, 'policy.reschedule_limit_reached'];
        yield 'started' => [0, 0, 'policy.already_started'];
    }

    /**
     * @dataProvider moves
     */
    public function testRescheduleDeadlineAndLimit(int $before, int $times, ?string $reason): void
    {
        $decision = self::evaluator()->reschedule(self::START, self::START - $before, $times);

        self::assertSame([null === $reason, $reason], [$decision->allowed, $decision->reasonCode]);
    }

    public function testInvalidPoliciesAreRefused(): void
    {
        $makers = [
            static fn () => new RefundTier(-1, 50),
            static fn () => new RefundTier(24, 101),
            static fn () => new CancellationPolicy(-1, []),
            static fn () => new ReschedulePolicy(null, -1),
        ];
        foreach ($makers as $make) {
            try {
                $make();
                self::fail('No exception.');
            } catch (InvalidValue $e) {
                self::assertSame('invalid_policy', $e->errorCode);
            }
        }
    }

    private static function evaluator(): PolicyEvaluator
    {
        return new PolicyEvaluator(
            new CancellationPolicy(24, [new RefundTier(24, 50), new RefundTier(48, 100)]),
            new ReschedulePolicy(12, 2)
        );
    }
}
