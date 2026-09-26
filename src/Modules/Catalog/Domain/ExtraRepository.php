<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

/**
 * The stored extras.
 * A deleted row is soft deleted (data-model §1) and no method returns it.
 */
interface ExtraRepository
{
    public function find(int $id): ?Extra;

    /**
     * @return list<Extra> In the order the admin lists them.
     */
    public function page(int $offset, int $limit): array;

    public function count(): int;

    /**
     * The extras of a service and those of every service, active or not.
     *
     * @return list<Extra> By id.
     */
    public function ofService(int $serviceId): array;

    /**
     * Inserts when the id is null, else updates the stored row, which the
     * caller has checked exists.
     *
     * @return Extra As stored, with its id.
     */
    public function save(Extra $extra): Extra;

    public function delete(int $id): void;

    /**
     * Deletes the extras of a service, which is being deleted.
     */
    public function deleteOfService(int $serviceId): void;
}
