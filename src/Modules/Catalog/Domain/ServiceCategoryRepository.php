<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

/**
 * The stored service categories.
 * A deleted row is soft deleted (data-model §1) and no method returns it.
 */
interface ServiceCategoryRepository
{
    public function find(int $id): ?ServiceCategory;

    /**
     * @return list<ServiceCategory> In the order the admin lists them.
     */
    public function page(int $offset, int $limit): array;

    public function count(): int;

    /**
     * Inserts when the id is null, else updates the stored row, which the
     * caller has checked exists.
     *
     * @return ServiceCategory As stored, with its id.
     */
    public function save(ServiceCategory $category): ServiceCategory;

    public function delete(int $id): void;
}
