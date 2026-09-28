<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Domain\Policy\CancellationPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\PolicyRepository;
use Vaqtyar\Modules\Booking\Domain\Policy\ReschedulePolicy;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * PolicyRepository for the admin screen: upsert on UNIQUE(type, service_id)
 * by delete-then-insert, one transaction, like
 * WpdbScheduleRuleRepository::replace(). A saved config always parses,
 * since PolicyAdminService only ever writes what fromConfig() accepts; a
 * row that does not (legacy data, a manual edit) reads as not set, the
 * same tolerance WpdbPolicyReader has on the booking-time path, so a GET
 * never 422s on a value nobody in this request submitted.
 */
final class WpdbPolicyRepository implements PolicyRepository
{
    private const CANCELLATION = 'cancellation';
    private const RESCHEDULE = 'reschedule';

    public function __construct(
        private readonly Db $db,
        private readonly Transaction $transaction,
        private readonly Clock $clock,
    ) {
    }

    public function findCancellation(int $serviceId): ?CancellationPolicy
    {
        $config = $this->find(self::CANCELLATION, $serviceId);
        if (null === $config) {
            return null;
        }
        try {
            return CancellationPolicy::fromConfig($config);
        } catch (InvalidValue) {
            return null;
        }
    }

    public function saveCancellation(int $serviceId, CancellationPolicy $policy): void
    {
        $this->save(self::CANCELLATION, $serviceId, $policy->toConfig());
    }

    public function deleteCancellation(int $serviceId): void
    {
        $this->delete(self::CANCELLATION, $serviceId);
    }

    public function findReschedule(int $serviceId): ?ReschedulePolicy
    {
        $config = $this->find(self::RESCHEDULE, $serviceId);
        if (null === $config) {
            return null;
        }
        try {
            return ReschedulePolicy::fromConfig($config);
        } catch (InvalidValue) {
            return null;
        }
    }

    public function saveReschedule(int $serviceId, ReschedulePolicy $policy): void
    {
        $this->save(self::RESCHEDULE, $serviceId, $policy->toConfig());
    }

    public function deleteReschedule(int $serviceId): void
    {
        $this->delete(self::RESCHEDULE, $serviceId);
    }

    /**
     * @return ?array<mixed>
     */
    private function find(string $type, int $serviceId): ?array
    {
        $rows = $this->db->getResults(
            'SELECT config FROM %i WHERE type = %s AND service_id = %d',
            Tables::name('policies'),
            $type,
            $serviceId
        );
        if ([] === $rows) {
            return null;
        }
        $config = \json_decode((new Row($rows[0]))->string('config'), true);

        return \is_array($config) ? $config : null;
    }

    /**
     * @param array<mixed> $config
     */
    private function save(string $type, int $serviceId, array $config): void
    {
        $this->transaction->run(function () use ($type, $serviceId, $config): void {
            $table = Tables::name('policies');
            $this->db->execute('DELETE FROM %i WHERE type = %s AND service_id = %d', $table, $type, $serviceId);
            $now = $this->now();
            $this->db->insert($table, [
                'type' => $type,
                'service_id' => $serviceId,
                'config' => (string) \wp_json_encode($config),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    private function delete(string $type, int $serviceId): void
    {
        $this->db->execute(
            'DELETE FROM %i WHERE type = %s AND service_id = %d',
            Tables::name('policies'),
            $type,
            $serviceId
        );
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
