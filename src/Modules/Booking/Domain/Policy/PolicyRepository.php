<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Policy;

/**
 * The policies table (data-model §2) for the admin screen: one row per type
 * and service, 0 being global, upserted on UNIQUE(type, service_id). Not
 * the booking-time read path (PolicyReader/WpdbPolicyReader), which merges
 * a service's own policy over the global one and tolerates a broken row.
 */
interface PolicyRepository
{
    public function findCancellation(int $serviceId): ?CancellationPolicy;

    public function saveCancellation(int $serviceId, CancellationPolicy $policy): void;

    public function deleteCancellation(int $serviceId): void;

    public function findReschedule(int $serviceId): ?ReschedulePolicy;

    public function saveReschedule(int $serviceId, ReschedulePolicy $policy): void;

    public function deleteReschedule(int $serviceId): void;

    /**
     * The stored config of a policy of $type (deposit, approval or booking_window), as it was
     * written; PolicyAdminService parses it. Null when this level has none, or it is not JSON.
     *
     * @return ?array<mixed>
     */
    public function findConfig(string $type, int $serviceId): ?array;

    /**
     * @param array<mixed> $config
     */
    public function saveConfig(string $type, int $serviceId, array $config): void;

    public function deleteConfig(string $type, int $serviceId): void;
}
