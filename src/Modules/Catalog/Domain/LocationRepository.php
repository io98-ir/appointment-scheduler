<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

/**
 * The stored locations.
 * A deleted row is soft deleted (data-model §1) and no method returns it.
 */
interface LocationRepository
{
    public function find(int $id): ?Location;

    /**
     * @return list<Location> In the order the admin lists them.
     */
    public function page(int $offset, int $limit): array;

    public function count(): int;

    /**
     * Inserts when the id is null, else updates the stored row, which the
     * caller has checked exists.
     *
     * @return Location As stored, with its id.
     */
    public function save(Location $location): Location;

    public function delete(int $id): void;

    /**
     * Whether staff or resources that are not deleted belong to it.
     */
    public function isReferenced(int $id): bool;
}
