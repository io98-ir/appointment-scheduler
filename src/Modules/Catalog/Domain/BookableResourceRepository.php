<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

/**
 * The stored resources.
 * A deleted row is soft deleted (data-model §1) and no method returns it.
 */
interface BookableResourceRepository
{
    public function find(int $id): ?BookableResource;

    /**
     * @return list<BookableResource> In the order the admin lists them.
     */
    public function page(int $offset, int $limit): array;

    public function count(): int;

    /**
     * Inserts when the id is null, else updates the stored row, which the
     * caller has checked exists.
     *
     * @return BookableResource As stored, with its id.
     */
    public function save(BookableResource $resource): BookableResource;

    public function delete(int $id): void;

    /**
     * @return list<BookableResource> The group's resources that are not deleted, by id.
     */
    public function inGroup(Slug $group): array;
}
