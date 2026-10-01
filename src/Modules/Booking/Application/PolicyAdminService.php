<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Policy\ApprovalPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\BookingWindowPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\CancellationPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\DepositPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\PolicyRepository;
use Vaqtyar\Modules\Booking\Domain\Policy\ReschedulePolicy;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
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

    /** The policies besides cancellation and rescheduling, kept as plain configs. */
    public const TERMS_TYPES = ['deposit', 'approval', 'booking_window'];

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
     * The deposit, approval or booking-window policy of a service (or the global one), as its stored
     * config, or null when this level has none or the row no longer parses.
     *
     * @return ?array<string, mixed>
     * @throws InvalidValue unknown_policy_type
     */
    public function terms(string $type, int $serviceId): ?array
    {
        $this->authorize($serviceId);
        $config = $this->policies->findConfig(self::checkedType($type), $serviceId);
        if (null === $config) {
            return null;
        }
        try {
            return self::parse($type, $config);
        } catch (InvalidValue) {
            return null;
        }
    }

    /**
     * @param array<mixed> $config what the client sent; only what the policy accepts is stored.
     * @return array<string, mixed> as stored.
     * @throws InvalidValue invalid_policy, or unknown_policy_type.
     */
    public function saveTerms(string $type, int $serviceId, array $config): array
    {
        $this->authorize($serviceId);
        $parsed = self::parse(self::checkedType($type), $config);
        $this->policies->saveConfig($type, $serviceId, $parsed);

        return $parsed;
    }

    public function deleteTerms(string $type, int $serviceId): void
    {
        $this->authorize($serviceId);
        $this->policies->deleteConfig(self::checkedType($type), $serviceId);
    }

    private static function checkedType(string $type): string
    {
        if (!\in_array($type, self::TERMS_TYPES, true)) {
            throw new InvalidValue('unknown_policy_type', 'There is no policy of this type.');
        }

        return $type;
    }

    /**
     * @param array<mixed> $config
     * @return array<string, mixed> the policy as it is stored.
     */
    private static function parse(string $type, array $config): array
    {
        return match ($type) {
            'deposit' => DepositPolicy::fromConfig($config)->toConfig(),
            'approval' => ApprovalPolicy::fromConfig($config)->toConfig(),
            default => BookingWindowPolicy::fromConfig($config)->toConfig(),
        };
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
