<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Field;

/**
 * The fields table for the admin CRUD screen (T3.5): flat, by row id, not
 * the booking-time read path (FieldReader/WpdbFieldReader), which merges
 * global and one service's fields and tolerates a broken row.
 */
interface FieldRepository
{
    /**
     * @return list<FieldDefinition> every global field, by sort then id.
     */
    public function globalFields(): array;

    /**
     * @return list<FieldDefinition> one service's own fields (not the
     *     globals), by sort then id.
     */
    public function forService(int $serviceId): array;

    public function find(int $id): ?FieldDefinition;

    /**
     * Inserts when $definition->id is null, else replaces the stored row.
     */
    public function save(FieldDefinition $definition): FieldDefinition;

    public function delete(int $id): void;
}
