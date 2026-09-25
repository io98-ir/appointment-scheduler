<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

/**
 * The stored services, each saved and loaded whole with its variants, staff
 * assignments and resource requirements (one aggregate).
 * A deleted row is soft deleted (data-model §1) and no method returns it.
 */
interface ServiceRepository
{
    public function find(int $id): ?Service;

    /**
     * @return list<Service> In the order the admin lists them.
     */
    public function page(int $offset, int $limit): array;

    public function count(): int;

    /**
     * Inserts when the id is null, else updates the stored row, which the
     * caller has checked exists. The variant ids must be the stored
     * service's own; stored variants left out are deleted.
     *
     * @return Service As stored, with its id.
     */
    public function save(Service $service): Service;

    public function delete(int $id): void;

    /**
     * The service a variant that is not deleted belongs to.
     */
    public function findByVariant(int $variantId): ?Service;
}
