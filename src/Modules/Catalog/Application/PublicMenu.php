<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Application;

use Vaqtyar\Modules\Catalog\Domain\LocationRepository;
use Vaqtyar\Modules\Catalog\Domain\Service;
use Vaqtyar\Modules\Catalog\Domain\ServiceCategoryRepository;
use Vaqtyar\Modules\Catalog\Domain\ServiceRepository;
use Vaqtyar\Modules\Catalog\Domain\StaffRepository;
use Vaqtyar\Modules\Catalog\Domain\Status;

/**
 * What a customer may pick from (T4.1): the active locations, the categories,
 * and the active services that have at least one bookable staff member, each
 * with its variants and the staff who serve it. Like CatalogApi it leaves out
 * anything deleted or inactive, and the staff of an inactive location. No
 * capability is checked, since the menu is public; it holds no customer data.
 *
 * Reads the first MAX_ITEMS of each list, so a site with more is cut off
 * rather than slow.
 *
 * @phpstan-type MenuMoney array{amount: int, currency: string}
 * @phpstan-type MenuVariant array{
 *     id: int, label: string, duration_min: int, price: MenuMoney, is_default: bool
 * }
 * @phpstan-type MenuStaff array{
 *     staff_id: int, name: string, title: string, location_id: int|null, variant_id: int|null,
 *     duration_min: int|null, price: MenuMoney|null
 * }
 * @phpstan-type MenuService array{
 *     id: int, name: string, category_id: int|null, description: string, capacity: int,
 *     variants: list<MenuVariant>, staff: list<MenuStaff>
 * }
 */
final class PublicMenu
{
    public const MAX_ITEMS = 200;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly StaffRepository $staff,
        private readonly LocationRepository $locations,
        private readonly ServiceCategoryRepository $categories,
    ) {
    }

    /**
     * @return array{
     *     locations: list<array{id: int, name: string, timezone: string, address: string}>,
     *     categories: list<array{id: int, name: string}>,
     *     services: list<MenuService>
     * }
     */
    public function build(): array
    {
        $open = [];
        $locations = [];
        foreach ($this->locations->page(0, self::MAX_ITEMS) as $location) {
            if (Status::Active === $location->status && null !== $location->id) {
                $open[$location->id] = true;
                $locations[] = [
                    'id' => $location->id,
                    'name' => $location->name->value,
                    'timezone' => $location->timezone->getName(),
                    'address' => $location->address,
                ];
            }
        }

        $categories = [];
        foreach ($this->categories->page(0, self::MAX_ITEMS) as $category) {
            if (null !== $category->id) {
                $categories[] = ['id' => $category->id, 'name' => $category->name->value];
            }
        }

        $services = [];
        $active = \array_values(\array_filter(
            $this->services->page(0, self::MAX_ITEMS),
            static fn (Service $service): bool => Status::Active === $service->status && null !== $service->id
        ));
        $staffIds = [];
        foreach ($active as $service) {
            foreach ($service->staff as $assignment) {
                $staffIds[$assignment->staffId] = $assignment->staffId;
            }
        }
        $members = [];
        foreach ($this->staff->findMany(\array_values($staffIds)) as $member) {
            if (null === $member->id || Status::Active !== $member->status) {
                continue;
            }
            if (null === $member->locationId || isset($open[$member->locationId])) {
                $members[$member->id] = $member;
            }
        }

        foreach ($active as $service) {
            $staff = [];
            foreach ($service->staff as $assignment) {
                $member = $members[$assignment->staffId] ?? null;
                if (null !== $member) {
                    $staff[] = [
                        'staff_id' => $assignment->staffId,
                        'name' => $member->name->value,
                        'title' => $member->title,
                        'location_id' => $member->locationId,
                        'variant_id' => $assignment->variantId,
                        'duration_min' => $assignment->durationMin,
                        'price' => $assignment->price?->toArray(),
                    ];
                }
            }
            if ([] === $staff || null === $service->id) {
                continue;
            }
            $services[] = [
                'id' => $service->id,
                'name' => $service->name->value,
                'category_id' => $service->categoryId,
                'description' => $service->description,
                'capacity' => $service->capacity,
                'variants' => \array_values(\array_filter(\array_map(
                    static fn ($v): ?array => null === $v->id ? null : [
                        'id' => $v->id,
                        'label' => $v->label,
                        'duration_min' => $v->durationMin,
                        'price' => $v->price->toArray(),
                        'is_default' => $v->isDefault,
                    ],
                    $service->variants
                ))),
                'staff' => $staff,
            ];
        }

        return ['locations' => $locations, 'categories' => $categories, 'services' => $services];
    }
}
