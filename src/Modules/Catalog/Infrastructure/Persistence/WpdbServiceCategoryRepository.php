<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Modules\Catalog\Domain\Color;
use Vaqtyar\Modules\Catalog\Domain\Name;
use Vaqtyar\Modules\Catalog\Domain\ServiceCategory;
use Vaqtyar\Modules\Catalog\Domain\ServiceCategoryRepository;
use Vaqtyar\Shared\Domain\Clock;

final class WpdbServiceCategoryRepository implements ServiceCategoryRepository
{
    private readonly CatalogTable $table;

    public function __construct(Db $db, Clock $clock)
    {
        $this->table = new CatalogTable($db, $clock, 'service_categories', 'sort, id');
    }

    public function find(int $id): ?ServiceCategory
    {
        $row = $this->table->find($id);

        return null === $row ? null : self::fromRow($row);
    }

    /**
     * @return list<ServiceCategory>
     */
    public function page(int $offset, int $limit): array
    {
        return \array_map(self::fromRow(...), $this->table->page($offset, $limit));
    }

    public function count(): int
    {
        return $this->table->count();
    }

    public function save(ServiceCategory $category): ServiceCategory
    {
        $columns = [
            'name' => $category->name->value,
            'color' => $category->color->value,
            'sort' => $category->sort,
        ];
        $id = $category->id;
        if (null === $id) {
            $id = $this->table->insert($columns);
        } else {
            $this->table->update($id, $columns);
        }

        return $this->find($id) ?? throw new \LogicException('The category just saved is gone.');
    }

    public function delete(int $id): void
    {
        $this->table->delete($id);
    }

    private static function fromRow(Row $row): ServiceCategory
    {
        return new ServiceCategory(
            $row->int('id'),
            Name::fromInput($row->string('name')),
            Color::fromInput($row->string('color')),
            $row->int('sort'),
        );
    }
}
