<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Catalog\Domain\ResourceRequirement;
use Vaqtyar\Modules\Catalog\Domain\Service;
use Vaqtyar\Modules\Catalog\Domain\ServiceRepository;
use Vaqtyar\Modules\Catalog\Domain\ServiceStaff;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Modules\Catalog\Domain\Variant;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\Slug;

/**
 * A service and its parts in four tables, written in one transaction so a
 * reader never sees half an aggregate. Variants are soft deleted, since
 * appointments point at them; staff assignments and resource requirements
 * are replaced whole.
 */
final class WpdbServiceRepository implements ServiceRepository
{
    private readonly CatalogTable $services;

    public function __construct(
        private readonly Db $db,
        private readonly Transaction $transaction,
        Clock $clock,
    ) {
        $this->services = new CatalogTable($db, $clock, 'services', 'sort, id');
    }

    public function find(int $id): ?Service
    {
        $row = $this->services->find($id);

        return null === $row ? null : ($this->withParts([$row])[0] ?? null);
    }

    public function findByVariant(int $variantId): ?Service
    {
        $serviceId = $this->db->getVar(
            'SELECT service_id FROM %i WHERE id = %d AND deleted_at IS NULL',
            Tables::name('service_variants'),
            $variantId
        );

        return null === $serviceId ? null : $this->find((int) $serviceId);
    }

    /**
     * @return list<Service>
     */
    public function page(int $offset, int $limit): array
    {
        return $this->withParts($this->services->page($offset, $limit));
    }

    public function count(): int
    {
        return $this->services->count();
    }

    /**
     * An update locks the service row first, so two saves of one service run
     * one after the other, and checks again under the lock what CatalogService
     * checked before it: the service is not deleted and the variant ids are
     * its own.
     */
    public function save(Service $service): Service
    {
        $id = $this->transaction->run(function () use ($service): int {
            $columns = [
                'category_id' => $service->categoryId,
                'name' => $service->name->value,
                'description' => $service->description,
                'image_id' => $service->imageId,
                'capacity' => $service->capacity,
                'status' => $service->status->value,
                'sort' => $service->sort,
            ];
            $id = $service->id;
            if (null === $id) {
                $id = $this->services->insert($columns);
            } else {
                $this->lock($id);
                $this->services->update($id, $columns);
            }
            $this->saveVariants($id, $service->variants);
            $this->replaceStaff($id, $service->staff);
            $this->replaceResources($id, $service->resources);

            return $id;
        });

        return $this->find($id) ?? throw new \LogicException('The service just saved is gone.');
    }

    /**
     * The variants and parts stay: the service row is what readers look up.
     */
    public function delete(int $id): void
    {
        $this->services->delete($id);
    }

    private function lock(int $serviceId): void
    {
        $locked = $this->db->getVar(
            'SELECT id FROM %i WHERE id = %d AND deleted_at IS NULL FOR UPDATE',
            $this->services->table(),
            $serviceId
        );
        if (null === $locked) {
            throw new NotFound('service_not_found', 'No service has this id.');
        }
    }

    /**
     * @param list<Variant> $variants
     */
    private function saveVariants(int $serviceId, array $variants): void
    {
        $table = Tables::name('service_variants');
        $now = $this->services->now();
        $stored = $this->variantIds($serviceId);
        $kept = [];
        foreach ($variants as $variant) {
            $columns = [
                'label' => $variant->label,
                'duration_min' => $variant->durationMin,
                'price' => $variant->price->amount,
                'buffer_before_min' => $variant->bufferBeforeMin,
                'buffer_after_min' => $variant->bufferAfterMin,
                'slot_step_min' => $variant->slotStepMin,
                'is_default' => (int) $variant->isDefault,
                'sort' => $variant->sort,
                'updated_at' => $now,
            ];
            if (null === $variant->id) {
                $kept[] = $this->db->insert(
                    $table,
                    $columns + ['service_id' => $serviceId, 'created_at' => $now]
                );
            } else {
                if (!\in_array($variant->id, $stored, true)) {
                    throw new InvalidValue('unknown_variant', 'A variant is not one of this service\'s.');
                }
                $this->db->update($table, $columns, ['id' => $variant->id, 'service_id' => $serviceId]);
                $kept[] = $variant->id;
            }
        }
        foreach ($stored as $storedId) {
            if (!\in_array($storedId, $kept, true)) {
                $this->db->update(
                    $table,
                    ['deleted_at' => $now, 'updated_at' => $now],
                    ['id' => $storedId, 'service_id' => $serviceId]
                );
            }
        }
    }

    /**
     * @return list<int>
     */
    private function variantIds(int $serviceId): array
    {
        $rows = $this->db->getResults(
            'SELECT id FROM %i WHERE service_id = %d AND deleted_at IS NULL',
            Tables::name('service_variants'),
            $serviceId
        );

        return \array_map(static fn (array $row): int => (new Row($row))->int('id'), $rows);
    }

    /**
     * @param list<ServiceStaff> $staff
     */
    private function replaceStaff(int $serviceId, array $staff): void
    {
        $table = Tables::name('service_staff');
        $now = $this->services->now();
        $this->db->execute('DELETE FROM %i WHERE service_id = %d', $table, $serviceId);
        foreach ($staff as $assignment) {
            $this->db->insert($table, [
                'service_id' => $serviceId,
                'staff_id' => $assignment->staffId,
                'variant_id' => $assignment->variantId,
                'price' => $assignment->price?->amount,
                'duration_min' => $assignment->durationMin,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param list<ResourceRequirement> $resources
     */
    private function replaceResources(int $serviceId, array $resources): void
    {
        $table = Tables::name('service_resources');
        $now = $this->services->now();
        $this->db->execute('DELETE FROM %i WHERE service_id = %d', $table, $serviceId);
        foreach ($resources as $requirement) {
            $this->db->insert($table, [
                'service_id' => $serviceId,
                'resource_group_key' => $requirement->groupKey->value,
                'quantity' => $requirement->quantity,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Loads the parts of all the services with one query per part table,
     * not one per service. A reference to a deleted staff member or category
     * is left out, so the service reads as saving it would leave it and the
     * same body saves again; the stale rows go on the next save.
     *
     * @param list<Row> $rows Service rows.
     * @return list<Service>
     */
    private function withParts(array $rows): array
    {
        if ([] === $rows) {
            return [];
        }
        $ids = \array_map(static fn (Row $row): int => $row->int('id'), $rows);
        $variants = $this->parts('%i p', ['service_variants'], $ids, 'AND p.deleted_at IS NULL ORDER BY p.sort, p.id');
        $staff = $this->parts(
            '%i p JOIN %i s ON s.id = p.staff_id AND s.deleted_at IS NULL',
            ['service_staff', 'staff'],
            $ids,
            'ORDER BY p.id'
        );
        $resources = $this->parts('%i p', ['service_resources'], $ids, 'ORDER BY p.id');
        $categories = $this->liveCategories($rows);

        return \array_map(
            static fn (Row $row): Service => new Service(
                $row->int('id'),
                Name::fromInput($row->string('name')),
                \array_map(self::variant(...), $variants[$row->int('id')] ?? []),
                \array_map(self::assignment(...), $staff[$row->int('id')] ?? []),
                \array_map(self::requirement(...), $resources[$row->int('id')] ?? []),
                \in_array($row->intOrNull('category_id'), $categories, true) ? $row->intOrNull('category_id') : null,
                $row->string('description'),
                $row->intOrNull('image_id'),
                $row->int('capacity'),
                Status::from($row->string('status')),
                $row->int('sort'),
            ),
            $rows
        );
    }

    /**
     * @param non-empty-list<Row> $rows Service rows.
     * @return list<int> The categories they name that are not deleted.
     */
    private function liveCategories(array $rows): array
    {
        $ids = \array_values(\array_filter(
            \array_map(static fn (Row $row): ?int => $row->intOrNull('category_id'), $rows)
        ));
        if ([] === $ids) {
            return [];
        }
        $in = \implode(',', \array_fill(0, \count($ids), '%d'));
        $found = $this->db->getResults(
            'SELECT id FROM %i WHERE id IN (' . $in . ') AND deleted_at IS NULL',
            Tables::name('service_categories'),
            ...$ids
        );

        return \array_map(static fn (array $row): int => (new Row($row))->int('id'), $found);
    }

    /**
     * The rows of a part table (alias p) for these services.
     *
     * @param literal-string $from The FROM clause, with %i for each table.
     * @param list<string> $tables Short names, for the %i in $from.
     * @param non-empty-list<int> $serviceIds
     * @param literal-string $rest The SQL after the service id condition.
     * @return array<int, list<Row>> By service id.
     */
    private function parts(string $from, array $tables, array $serviceIds, string $rest): array
    {
        // One %d per id, so the ids still go through prepare().
        $in = \implode(',', \array_fill(0, \count($serviceIds), '%d'));
        $rows = $this->db->getResults(
            'SELECT p.* FROM ' . $from . ' WHERE p.service_id IN (' . $in . ') ' . $rest,
            ...\array_map(Tables::name(...), $tables),
            ...$serviceIds
        );
        $byService = [];
        foreach ($rows as $values) {
            $row = new Row($values);
            $byService[$row->int('service_id')][] = $row;
        }

        return $byService;
    }

    private static function variant(Row $row): Variant
    {
        return new Variant(
            $row->int('id'),
            $row->string('label'),
            $row->int('duration_min'),
            Money::ofRial($row->int('price')),
            1 === $row->int('is_default'),
            $row->int('buffer_before_min'),
            $row->int('buffer_after_min'),
            $row->intOrNull('slot_step_min'),
            $row->int('sort'),
        );
    }

    private static function assignment(Row $row): ServiceStaff
    {
        $price = $row->intOrNull('price');

        return new ServiceStaff(
            $row->int('staff_id'),
            $row->intOrNull('variant_id'),
            null === $price ? null : Money::ofRial($price),
            $row->intOrNull('duration_min'),
        );
    }

    private static function requirement(Row $row): ResourceRequirement
    {
        return new ResourceRequirement(Slug::fromInput($row->string('resource_group_key')), $row->int('quantity'));
    }
}
