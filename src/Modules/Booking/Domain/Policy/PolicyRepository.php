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
}
