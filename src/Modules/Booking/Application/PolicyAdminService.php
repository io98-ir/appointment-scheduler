<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Policy\CancellationPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\PolicyRepository;
use Vaqtyar\Modules\Booking\Domain\Policy\ReschedulePolicy;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\NotFound;

/**
 * The admin's policy use cases (implementation-notes §4.12): reading,
 * saving and clearing the cancellation and reschedule policy of a service,
 * or the global one (service_id 0). Each checks the capability again,
 * after the REST permission callback (architecture §12). The same
 * capability as booking, not a new one: whoever can manage bookings sets
 * the rules for them.
 */
final class PolicyAdminService
{
    public const CAPABILITY = BookingService::CAPABILITY;

    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly CatalogApi $catalog,
        private readonly PolicyRepository $policies,
    ) {
    }

    public function cancellation(int $serviceId): ?CancellationPolicy
    {
        $this->authorize($serviceId);

        return $this->policies->findCancellation($serviceId);
    }

    public function saveCancellation(int $serviceId, CancellationPolicy $policy): CancellationPolicy
    {
        $this->authorize($serviceId);
        $this->policies->saveCancellation($serviceId, $policy);

        return $policy;
    }

    public function deleteCancellation(int $serviceId): void
    {
        $this->authorize($serviceId);
        $this->policies->deleteCancellation($serviceId);
    }

    public function reschedule(int $serviceId): ?ReschedulePolicy
    {
        $this->authorize($serviceId);

        return $this->policies->findReschedule($serviceId);
    }

    public function saveReschedule(int $serviceId, ReschedulePolicy $policy): ReschedulePolicy
    {
        $this->authorize($serviceId);
        $this->policies->saveReschedule($serviceId, $policy);

        return $policy;
    }

    public function deleteReschedule(int $serviceId): void
    {
        $this->authorize($serviceId);
        $this->policies->deleteReschedule($serviceId);
    }

    /**
     * 0 is the global policy, always writable; a non-zero id must name a
     * service, which may be inactive (its policy can still be edited).
     */
    private function authorize(int $serviceId): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
        if (0 !== $serviceId && !$this->catalog->isStored('service', $serviceId)) {
            throw new NotFound('service_not_found', 'No service has this id.');
        }
    }
}
