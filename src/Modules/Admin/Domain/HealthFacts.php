<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Domain;

/**
 * What the server looks like, gathered once (StatusSource) and judged by the
 * HealthEvaluator, so the judging needs no WordPress or database.
 */
final class HealthFacts
{
    public function __construct(
        public readonly bool $hasIntl,
        public readonly bool $hasSodium,
        public readonly bool $hasInnoDb,
        /** UTC offset of Asia/Tehran in January, in seconds; null when the zone is unknown. */
        public readonly ?int $tehranWinterOffset,
        /** The same in July: differs from winter when the host's tzdata still has Iran's old DST. */
        public readonly ?int $tehranSummerOffset,
        /** True when a real system cron runs WordPress's cron (DISABLE_WP_CRON). */
        public readonly bool $realCron,
        /** Action Scheduler actions waiting to run. */
        public readonly int $queuePending,
        /** Actions that ran late by more than QUEUE_LATE_SECONDS and still wait. */
        public readonly int $queueLate,
        public readonly int $queueFailed,
    ) {
    }
}
