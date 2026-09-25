<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

/**
 * The stored staff members.
 * A deleted row is soft deleted (data-model §1) and no method returns it.
 */
interface StaffRepository
{
    public function find(int $id): ?Staff;

    /**
     * @return list<Staff> In the order the admin lists them.
     */
    public function page(int $offset, int $limit): array;

    public function count(): int;

    /**
     * Inserts when the id is null, else updates the stored row, which the
     * caller has checked exists.
     *
     * @return Staff As stored, with its id.
     */
    public function save(Staff $staff): Staff;

    public function delete(int $id): void;

    /**
     * @param list<int> $ids
     * @return list<Staff> Those of them that are not deleted, in no given order.
     */
    public function findMany(array $ids): array;
}
