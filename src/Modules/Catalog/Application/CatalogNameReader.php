<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Application;

use Vaqtyar\Modules\Catalog\Contracts\CatalogNames;
use Vaqtyar\Modules\Catalog\Domain\LocationRepository;
use Vaqtyar\Modules\Catalog\Domain\ServiceRepository;
use Vaqtyar\Modules\Catalog\Domain\StaffRepository;

/**
 * CatalogNames over the repositories.
 */
final class CatalogNameReader implements CatalogNames
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly StaffRepository $staff,
        private readonly LocationRepository $locations,
    ) {
    }

    public function serviceName(int $serviceId): ?string
    {
        return $this->services->find($serviceId)?->name->value;
    }

    public function locationName(int $locationId): ?string
    {
        return $this->locations->find($locationId)?->name->value;
    }

    /**
     * @return ?array{name: string, email: ?string}
     */
    public function staffContact(int $staffId): ?array
    {
        $member = $this->staff->find($staffId);

        return null === $member ? null : [
            'name' => $member->name->value,
            'email' => $member->email?->value,
        ];
    }
}
