<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\PolicyReader;
use Vaqtyar\Modules\Booking\Domain\Policy\CancellationPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\PolicyEvaluator;
use Vaqtyar\Modules\Booking\Domain\Policy\ReschedulePolicy;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * PolicyReader on the policies table. Configs:
 * cancellation {"notice_hours": int|null, "refund": [{"hours": int, "percent": int}]},
 * reschedule {"notice_hours": int|null, "max_times": int|null}.
 * The admin screen (T3.5) writes only what the Domain accepts; a row that
 * no longer parses is skipped, like a broken price rule, so the next level
 * (global, then lenient) applies.
 */
final class WpdbPolicyReader implements PolicyReader
{
    private const CANCELLATION = 'cancellation';
    private const RESCHEDULE = 'reschedule';

    public function __construct(private readonly Db $db)
    {
    }

    public function forService(int $serviceId): PolicyEvaluator
    {
        // The service's own row sorts first, so it wins over the global one (0).
        $rows = $this->db->getResults(
            'SELECT type, config FROM %i WHERE type IN (%s, %s) AND service_id IN (0, %d) ORDER BY service_id DESC',
            Tables::name('policies'),
            self::CANCELLATION,
            self::RESCHEDULE,
            $serviceId
        );
        $cancellation = null;
        $reschedule = null;
        foreach ($rows as $values) {
            $row = new Row($values);
            $config = \json_decode($row->string('config'), true);
            if (!\is_array($config)) {
                continue;
            }
            try {
                if (self::CANCELLATION === $row->string('type')) {
                    $cancellation ??= CancellationPolicy::fromConfig($config);
                } else {
                    $reschedule ??= ReschedulePolicy::fromConfig($config);
                }
            } catch (InvalidValue) {
                continue;
            }
        }

        return new PolicyEvaluator(
            $cancellation ?? CancellationPolicy::lenient(),
            $reschedule ?? ReschedulePolicy::lenient()
        );
    }
}
