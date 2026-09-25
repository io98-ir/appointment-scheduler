<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Modules\Catalog\Domain\BookableResource;
use Vaqtyar\Modules\Catalog\Domain\BookableResourceRepository;
use Vaqtyar\Modules\Catalog\Domain\Name;
use Vaqtyar\Modules\Catalog\Domain\Slug;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Shared\Domain\Clock;

final class WpdbBookableResourceRepository implements BookableResourceRepository
{
    private readonly CatalogTable $table;

    public function __construct(private readonly Db $db, Clock $clock)
    {
        $this->table = new CatalogTable($db, $clock, 'resources', 'id');
    }

    public function find(int $id): ?BookableResource
    {
        $row = $this->table->find($id);

        return null === $row ? null : self::fromRow($row);
    }

    /**
     * @return list<BookableResource>
     */
    public function page(int $offset, int $limit): array
    {
        return \array_map(self::fromRow(...), $this->table->page($offset, $limit));
    }

    public function count(): int
    {
        return $this->table->count();
    }

    public function save(BookableResource $resource): BookableResource
    {
        $columns = [
            'location_id' => $resource->locationId,
            'group_key' => $resource->groupKey->value,
            'name' => $resource->name->value,
            'capacity' => $resource->capacity,
            'status' => $resource->status->value,
        ];
        $id = $resource->id;
        if (null === $id) {
            $id = $this->table->insert($columns);
        } else {
            $this->table->update($id, $columns);
        }

        return $this->find($id) ?? throw new \LogicException('The resource just saved is gone.');
    }

    public function delete(int $id): void
    {
        $this->table->delete($id);
    }

    /**
     * @return list<BookableResource>
     */
    public function inGroup(Slug $group): array
    {
        $rows = $this->db->getResults(
            'SELECT * FROM %i WHERE group_key = %s AND deleted_at IS NULL ORDER BY id',
            $this->table->table(),
            $group->value
        );

        return \array_map(static fn (array $row): BookableResource => self::fromRow(new Row($row)), $rows);
    }

    private static function fromRow(Row $row): BookableResource
    {
        return new BookableResource(
            $row->int('id'),
            Name::fromInput($row->string('name')),
            Slug::fromInput($row->string('group_key')),
            $row->intOrNull('location_id'),
            $row->int('capacity'),
            Status::from($row->string('status')),
        );
    }
}
