<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Admin;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Admin\Domain\HealthEvaluator;
use Vaqtyar\Modules\Admin\Domain\HealthFacts;
use Vaqtyar\Modules\Admin\Domain\HealthStatus;

final class HealthEvaluatorTest extends TestCase
{
    public function testAHealthyServerPassesEveryCheck(): void
    {
        foreach (self::statuses(self::facts()) as $status) {
            self::assertSame(HealthStatus::Good, $status);
        }
    }

    public function testReturnsTheCheckIdsInTheirDeclaredOrder(): void
    {
        self::assertSame(HealthEvaluator::IDS, \array_keys(self::statuses(self::facts())));
    }

    /**
     * @return iterable<string, array{HealthFacts, string, HealthStatus}>
     */
    public static function problems(): iterable
    {
        yield 'no InnoDB' => [self::facts(hasInnoDb: false), 'database_engine', HealthStatus::Critical];
        yield 'no sodium' => [self::facts(hasSodium: false), 'sodium', HealthStatus::Critical];
        yield 'no intl' => [self::facts(hasIntl: false), 'intl', HealthStatus::Recommended];
        yield 'old tzdata with DST' => [self::facts(summer: 16200), 'timezone_data', HealthStatus::Critical];
        yield 'unknown zone' => [self::facts(winter: null, summer: null), 'timezone_data', HealthStatus::Critical];
        yield 'page-visit cron' => [self::facts(realCron: false), 'cron', HealthStatus::Recommended];
        yield 'failed jobs' => [self::facts(failed: 2), 'queue', HealthStatus::Recommended];
        yield 'late jobs' => [self::facts(late: 1, failed: 2), 'queue', HealthStatus::Critical];
        yield 'pending jobs alone' => [self::facts(pending: 500), 'queue', HealthStatus::Good];
    }

    /**
     * @dataProvider problems
     */
    public function testJudgesEachCheck(HealthFacts $facts, string $id, HealthStatus $expected): void
    {
        self::assertSame($expected, self::statuses($facts)[$id]);
    }

    /**
     * @return array<string, HealthStatus>
     */
    private static function statuses(HealthFacts $facts): array
    {
        $statuses = [];
        foreach ((new HealthEvaluator())->evaluate($facts) as $check) {
            $statuses[$check->id] = $check->status;
        }

        return $statuses;
    }

    private static function facts(
        bool $hasIntl = true,
        bool $hasSodium = true,
        bool $hasInnoDb = true,
        ?int $winter = 12600,
        ?int $summer = 12600,
        bool $realCron = true,
        int $pending = 0,
        int $late = 0,
        int $failed = 0,
    ): HealthFacts {
        return new HealthFacts($hasIntl, $hasSodium, $hasInnoDb, $winter, $summer, $realCron, $pending, $late, $failed);
    }
}
