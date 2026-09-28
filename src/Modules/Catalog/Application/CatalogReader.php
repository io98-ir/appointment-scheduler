<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Application;

use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Catalog\Contracts\ExtraOffer;
use Vaqtyar\Modules\Catalog\Contracts\LocationInfo;
use Vaqtyar\Modules\Catalog\Contracts\Offer;
use Vaqtyar\Modules\Catalog\Contracts\ResourceNeed;
use Vaqtyar\Modules\Catalog\Contracts\ResourceUnit;
use Vaqtyar\Modules\Catalog\Contracts\StaffOffer;
use Vaqtyar\Modules\Catalog\Domain\BookableResourceRepository;
use Vaqtyar\Modules\Catalog\Domain\Extra;
use Vaqtyar\Modules\Catalog\Domain\ExtraRepository;
use Vaqtyar\Modules\Catalog\Domain\LocationRepository;
use Vaqtyar\Modules\Catalog\Domain\ResourceRequirement;
use Vaqtyar\Modules\Catalog\Domain\ServiceRepository;
use Vaqtyar\Modules\Catalog\Domain\StaffRepository;
use Vaqtyar\Modules\Catalog\Domain\Status;

/**
 * The catalog as other modules see it (CatalogApi): bookable items only. A
 * staff member or resource at an inactive location is not bookable either.
 */
final class CatalogReader implements CatalogApi
{
    /** @var array<int, bool> Whether each location looked up is active, for one call. */
    private array $activeLocations = [];

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly StaffRepository $staff,
        private readonly BookableResourceRepository $resources,
        private readonly LocationRepository $locations,
        private readonly ExtraRepository $extras,
    ) {
    }

    public function offer(int $variantId): ?Offer
    {
        $this->activeLocations = [];
        $service = $this->services->findByVariant($variantId);
        if (null === $service || Status::Active !== $service->status || null === $service->id) {
            return null;
        }
        $variant = \array_values(\array_filter(
            $service->variants,
            static fn ($candidate): bool => $candidate->id === $variantId
        ))[0] ?? null;
        if (null === $variant) {
            return null;
        }

        $staffIds = \array_values(\array_unique(\array_map(static fn ($a): int => $a->staffId, $service->staff)));
        $staff = [];
        foreach ($this->staff->findMany($staffIds) as $member) {
            $terms = null === $member->id ? null : $service->terms($variantId, $member->id);
            if (null !== $terms && Status::Active === $member->status && $this->isOpen($member->locationId)) {
                $staff[$member->id] = new StaffOffer(
                    $member->id,
                    $member->locationId,
                    $terms->durationMin,
                    $terms->price,
                    $member->sort
                );
            }
        }
        \ksort($staff);

        return new Offer(
            $service->id,
            $variantId,
            $service->capacity,
            $variant->bufferBeforeMin,
            $variant->bufferAfterMin,
            $variant->slotStepMin,
            \array_values($staff),
            \array_map($this->need(...), $service->resources),
            \array_values(\array_map(
                static fn (Extra $extra): ExtraOffer => new ExtraOffer(
                    $extra->id ?? throw new \LogicException('A stored extra has an id.'),
                    $extra->durationMin,
                    $extra->price,
                    $extra->maxQty
                ),
                \array_filter(
                    $this->extras->ofService($service->id),
                    static fn (Extra $extra): bool => Status::Active === $extra->status
                )
            )),
        );
    }

    public function location(int $locationId): ?LocationInfo
    {
        $location = $this->locations->find($locationId);

        return null === $location || Status::Active !== $location->status ? null : new LocationInfo(
            $locationId,
            $location->timezone,
            $location->holidayCalendar?->value,
        );
    }

    public function isStored(string $kind, int $id): bool
    {
        return match ($kind) {
            'staff' => null !== $this->staff->find($id),
            'resource' => null !== $this->resources->find($id),
            'location' => null !== $this->locations->find($id),
            default => false,
        };
    }

    /**
     * Null is every location, which is always open.
     */
    private function isOpen(?int $locationId): bool
    {
        if (null === $locationId) {
            return true;
        }

        return $this->activeLocations[$locationId]
            ??= Status::Active === $this->locations->find($locationId)?->status;
    }

    private function need(ResourceRequirement $requirement): ResourceNeed
    {
        $units = [];
        foreach ($this->resources->inGroup($requirement->groupKey) as $resource) {
            $bookable = Status::Active === $resource->status && $this->isOpen($resource->locationId);
            if ($bookable && null !== $resource->id) {
                $units[] = new ResourceUnit($resource->id, $resource->locationId, $resource->capacity);
            }
        }

        return new ResourceNeed($requirement->groupKey->value, $requirement->quantity, $units);
    }
}
