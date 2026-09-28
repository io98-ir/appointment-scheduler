<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Domain\Policy;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Domain\Policy\CancellationPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\RefundTier;
use Vaqtyar\Modules\Booking\Domain\Policy\ReschedulePolicy;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * The (de)serialization the policies table's JSON config goes through
 * (implementation-notes §4.12), shared by WpdbPolicyReader (booking time,
 * a broken row is skipped) and the admin repository (write time, a broken
 * config is a 422).
 */
final class PolicyConfigTest extends TestCase
{
    public function testACancellationPolicyRoundTripsThroughItsConfig(): void
    {
        $policy = new CancellationPolicy(24, [new RefundTier(48, 100), new RefundTier(24, 50)]);

        self::assertEquals($policy, CancellationPolicy::fromConfig($policy->toConfig()));
    }

    public function testACancellationPolicyWithNoNoticeAndNoTiersRoundTrips(): void
    {
        $policy = new CancellationPolicy(null, []);

        self::assertSame(['notice_hours' => null, 'refund' => []], $policy->toConfig());
        self::assertEquals($policy, CancellationPolicy::fromConfig($policy->toConfig()));
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function brokenCancellationConfigs(): iterable
    {
        yield 'notice_hours is not a number' => [['notice_hours' => '24']];
        yield 'refund is not a list' => [['refund' => 'a lot']];
        yield 'a tier is missing percent' => [['refund' => [['hours' => 24]]]];
        yield 'a tier percent is not a number' => [['refund' => [['hours' => 24, 'percent' => '50']]]];
        yield 'a tier is not an object' => [['refund' => ['a lot']]];
        // A broken refund throws even next to a valid notice_hours: the row
        // is invalid as a whole, so WpdbPolicyReader skips it entirely and
        // falls back a level, rather than keeping just the valid half.
        yield 'a valid notice_hours next to a broken refund' => [
            ['notice_hours' => 24, 'refund' => 'a lot'],
        ];
    }

    /**
     * @dataProvider brokenCancellationConfigs
     * @param array<mixed> $config
     */
    public function testABrokenCancellationConfigIsRefused(array $config): void
    {
        try {
            CancellationPolicy::fromConfig($config);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('invalid_policy', $e->errorCode);
        }
    }

    public function testAReschedulePolicyRoundTripsThroughItsConfig(): void
    {
        $policy = new ReschedulePolicy(12, 2);

        self::assertSame(['notice_hours' => 12, 'max_times' => 2], $policy->toConfig());
        self::assertEquals($policy, ReschedulePolicy::fromConfig($policy->toConfig()));
    }

    public function testALenientReschedulePolicyRoundTrips(): void
    {
        $policy = ReschedulePolicy::lenient();

        self::assertEquals($policy, ReschedulePolicy::fromConfig($policy->toConfig()));
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function brokenRescheduleConfigs(): iterable
    {
        yield 'notice_hours is not a number' => [['notice_hours' => 'soon']];
        yield 'max_times is not a number' => [['max_times' => 'twice']];
    }

    /**
     * @dataProvider brokenRescheduleConfigs
     * @param array<mixed> $config
     */
    public function testABrokenRescheduleConfigIsRefused(array $config): void
    {
        try {
            ReschedulePolicy::fromConfig($config);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('invalid_policy', $e->errorCode);
        }
    }
}
