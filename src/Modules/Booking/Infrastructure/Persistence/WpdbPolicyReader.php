<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\PolicyReader;
use Vaqtyar\Modules\Booking\Application\TermsReader;
use Vaqtyar\Modules\Booking\Domain\Policy\ApprovalPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\BookingTerms;
use Vaqtyar\Modules\Booking\Domain\Policy\BookingWindowPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\CancellationPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\DepositPolicy;
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
final class WpdbPolicyReader implements PolicyReader, TermsReader
{
    private const CANCELLATION = 'cancellation';
    private const RESCHEDULE = 'reschedule';
    private const DEPOSIT = 'deposit';
    private const APPROVAL = 'approval';
    private const WINDOW = 'booking_window';

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

    public function termsFor(int $serviceId): BookingTerms
    {
        // The service's own row sorts first, so it wins over the global one (0).
        $rows = $this->db->getResults(
            'SELECT type, config FROM %i WHERE type IN (%s, %s, %s) AND service_id IN (0, %d) '
            . 'ORDER BY service_id DESC',
            Tables::name('policies'),
            self::DEPOSIT,
            self::APPROVAL,
            self::WINDOW,
            $serviceId
        );
        $deposit = null;
        $approval = null;
        $window = null;
        foreach ($rows as $values) {
            $row = new Row($values);
            $config = \json_decode($row->string('config'), true);
            if (!\is_array($config)) {
                continue;
            }
            try {
                match ($row->string('type')) {
                    self::DEPOSIT => $deposit ??= DepositPolicy::fromConfig($config),
                    self::APPROVAL => $approval ??= ApprovalPolicy::fromConfig($config),
                    default => $window ??= BookingWindowPolicy::fromConfig($config),
                };
            } catch (InvalidValue) {
                // A row that no longer parses is skipped: the next level, or nothing, applies.
                continue;
            }
        }

        return new BookingTerms(
            $deposit ?? DepositPolicy::lenient(),
            $approval ?? ApprovalPolicy::lenient(),
            $window ?? BookingWindowPolicy::lenient()
        );
    }
}
