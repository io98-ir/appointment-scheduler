<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Application;

use Vaqtyar\Modules\Catalog\Domain\BookableResource;
use Vaqtyar\Modules\Catalog\Domain\BookableResourceRepository;
use Vaqtyar\Modules\Catalog\Domain\Extra;
use Vaqtyar\Modules\Catalog\Domain\ExtraRepository;
use Vaqtyar\Modules\Catalog\Domain\Location;
use Vaqtyar\Modules\Catalog\Domain\LocationRepository;
use Vaqtyar\Modules\Catalog\Domain\Service;
use Vaqtyar\Modules\Catalog\Domain\ServiceCategory;
use Vaqtyar\Modules\Catalog\Domain\ServiceCategoryRepository;
use Vaqtyar\Modules\Catalog\Domain\ServiceRepository;
use Vaqtyar\Modules\Catalog\Domain\Staff;
use Vaqtyar\Modules\Catalog\Domain\StaffRepository;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\NotFound;

/**
 * The admin's catalog use cases. Each checks the capability again, after
 * the REST permission callback (architecture §12), and the references
 * between aggregates, which no single aggregate can see.
 *
 * The reference checks are not atomic with the save: an item deleted in
 * between leaves a reference to a soft-deleted row. Readers skip it: a
 * service reads without a deleted category or staff member, and CatalogApi
 * offers nothing deleted. The service save repeats its own checks under a
 * row lock (WpdbServiceRepository).
 */
final class CatalogService
{
    public const CAPABILITY = 'manage_catalog';

    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly LocationRepository $locations,
        private readonly StaffRepository $staffMembers,
        private readonly BookableResourceRepository $resources,
        private readonly ServiceCategoryRepository $categories,
        private readonly ServiceRepository $services,
        private readonly ExtraRepository $extras,
    ) {
    }

    public function location(int $id): Location
    {
        $this->authorize();

        return $this->locations->find($id) ?? throw self::notFound('location');
    }

    /**
     * @return Page<Location>
     */
    public function locations(int $offset, int $limit): Page
    {
        $this->authorize();

        return new Page($this->locations->page($offset, $limit), $this->locations->count());
    }

    public function saveLocation(Location $location): Location
    {
        $this->authorize();
        if (null !== $location->id) {
            $this->locations->find($location->id) ?? throw self::notFound('location');
        }

        return $this->locations->save($location);
    }

    public function deleteLocation(int $id): void
    {
        $this->location($id);
        if ($this->locations->isReferenced($id)) {
            throw new InvalidValue('location_in_use', 'Staff or resources still belong to the location.');
        }
        $this->locations->delete($id);
    }

    public function staffMember(int $id): Staff
    {
        $this->authorize();

        return $this->staffMembers->find($id) ?? throw self::notFound('staff');
    }

    /**
     * @return Page<Staff>
     */
    public function staff(int $offset, int $limit): Page
    {
        $this->authorize();

        return new Page($this->staffMembers->page($offset, $limit), $this->staffMembers->count());
    }

    public function saveStaff(Staff $staff): Staff
    {
        $this->authorize();
        if (null !== $staff->id) {
            $this->staffMembers->find($staff->id) ?? throw self::notFound('staff');
        }
        $this->assertLocation($staff->locationId);

        return $this->staffMembers->save($staff);
    }

    public function deleteStaff(int $id): void
    {
        $this->staffMember($id);
        $this->staffMembers->delete($id);
    }

    public function resource(int $id): BookableResource
    {
        $this->authorize();

        return $this->resources->find($id) ?? throw self::notFound('resource');
    }

    /**
     * @return Page<BookableResource>
     */
    public function resources(int $offset, int $limit): Page
    {
        $this->authorize();

        return new Page($this->resources->page($offset, $limit), $this->resources->count());
    }

    public function saveResource(BookableResource $resource): BookableResource
    {
        $this->authorize();
        if (null !== $resource->id) {
            $this->resources->find($resource->id) ?? throw self::notFound('resource');
        }
        $this->assertLocation($resource->locationId);

        return $this->resources->save($resource);
    }

    public function deleteResource(int $id): void
    {
        $this->resource($id);
        $this->resources->delete($id);
    }

    public function category(int $id): ServiceCategory
    {
        $this->authorize();

        return $this->categories->find($id) ?? throw self::notFound('category');
    }

    /**
     * @return Page<ServiceCategory>
     */
    public function categories(int $offset, int $limit): Page
    {
        $this->authorize();

        return new Page($this->categories->page($offset, $limit), $this->categories->count());
    }

    public function saveCategory(ServiceCategory $category): ServiceCategory
    {
        $this->authorize();
        if (null !== $category->id) {
            $this->categories->find($category->id) ?? throw self::notFound('category');
        }

        return $this->categories->save($category);
    }

    /**
     * Its services stay and read as uncategorized (ServiceRepository).
     */
    public function deleteCategory(int $id): void
    {
        $this->category($id);
        $this->categories->delete($id);
    }

    public function service(int $id): Service
    {
        $this->authorize();

        return $this->services->find($id) ?? throw self::notFound('service');
    }

    /**
     * @return Page<Service>
     */
    public function services(int $offset, int $limit): Page
    {
        $this->authorize();

        return new Page($this->services->page($offset, $limit), $this->services->count());
    }

    /**
     * A stored variant can only be kept by its own service: the ids a
     * request names must be the stored service's. Variants left out are
     * deleted.
     */
    public function saveService(Service $service): Service
    {
        $this->authorize();
        $stored = null === $service->id
            ? null
            : $this->services->find($service->id) ?? throw self::notFound('service');
        $storedVariantIds = \array_map(static fn ($variant) => $variant->id, $stored->variants ?? []);
        foreach ($service->variants as $variant) {
            if (null !== $variant->id && !\in_array($variant->id, $storedVariantIds, true)) {
                throw new InvalidValue('unknown_variant', 'A variant is not one of this service\'s.');
            }
        }
        if (null !== $service->categoryId && null === $this->categories->find($service->categoryId)) {
            throw new InvalidValue('unknown_category', 'The category does not exist.');
        }
        $staffIds = \array_unique(\array_map(static fn ($assignment) => $assignment->staffId, $service->staff));
        foreach ($staffIds as $staffId) {
            if (null === $this->staffMembers->find($staffId)) {
                throw new InvalidValue('unknown_staff', 'An assigned staff member does not exist.');
            }
        }

        return $this->services->save($service);
    }

    /**
     * Its extras go with it: they would name a service no save accepts. Its
     * variants stay, unreachable, since readers look the service up first.
     */
    public function deleteService(int $id): void
    {
        $this->service($id);
        $this->services->delete($id);
        $this->extras->deleteOfService($id);
    }

    public function extra(int $id): Extra
    {
        $this->authorize();

        return $this->extras->find($id) ?? throw self::notFound('extra');
    }

    /**
     * @return Page<Extra>
     */
    public function extras(int $offset, int $limit): Page
    {
        $this->authorize();

        return new Page($this->extras->page($offset, $limit), $this->extras->count());
    }

    public function saveExtra(Extra $extra): Extra
    {
        $this->authorize();
        if (null !== $extra->id) {
            $this->extras->find($extra->id) ?? throw self::notFound('extra');
        }
        if (null !== $extra->serviceId && null === $this->services->find($extra->serviceId)) {
            throw new InvalidValue('unknown_service', 'The service does not exist.');
        }

        return $this->extras->save($extra);
    }

    public function deleteExtra(int $id): void
    {
        $this->extra($id);
        $this->extras->delete($id);
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }

    private function assertLocation(?int $locationId): void
    {
        if (null !== $locationId && null === $this->locations->find($locationId)) {
            throw new InvalidValue('unknown_location', 'The location does not exist.');
        }
    }

    private static function notFound(string $item): NotFound
    {
        return new NotFound("{$item}_not_found", "No {$item} has this id.");
    }
}
