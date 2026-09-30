<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Domain;

/**
 * Judges the server for what the plugin needs (architecture §13). Iran has
 * had no DST since 2023 (principles §5), so a host whose tzdata predates that
 * would shift local times by an hour twice a year.
 */
final class HealthEvaluator
{
    /** Asia/Tehran: UTC+3:30 all year. */
    public const TEHRAN_OFFSET = 12600;

    /** An action this long past its time means the queue is not running. */
    public const QUEUE_LATE_SECONDS = 900;

    /** The ids evaluate() returns, in order. */
    public const IDS = ['database_engine', 'sodium', 'intl', 'timezone_data', 'cron', 'queue'];

    /**
     * @return list<HealthCheck>
     */
    public function evaluate(HealthFacts $facts): array
    {
        return [
            new HealthCheck('database_engine', $facts->hasInnoDb ? HealthStatus::Good : HealthStatus::Critical),
            new HealthCheck('sodium', $facts->hasSodium ? HealthStatus::Good : HealthStatus::Critical),
            new HealthCheck('intl', $facts->hasIntl ? HealthStatus::Good : HealthStatus::Recommended),
            new HealthCheck('timezone_data', $this->timezone($facts)),
            new HealthCheck('cron', $facts->realCron ? HealthStatus::Good : HealthStatus::Recommended),
            new HealthCheck('queue', $this->queue($facts)),
        ];
    }

    private function timezone(HealthFacts $facts): HealthStatus
    {
        return self::TEHRAN_OFFSET === $facts->tehranWinterOffset
            && self::TEHRAN_OFFSET === $facts->tehranSummerOffset
            ? HealthStatus::Good
            : HealthStatus::Critical;
    }

    private function queue(HealthFacts $facts): HealthStatus
    {
        if ($facts->queueLate > 0) {
            return HealthStatus::Critical;
        }

        return $facts->queueFailed > 0 ? HealthStatus::Recommended : HealthStatus::Good;
    }
}
