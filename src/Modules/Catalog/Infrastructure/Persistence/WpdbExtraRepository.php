<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Modules\Catalog\Domain\Extra;
use Vaqtyar\Modules\Catalog\Domain\ExtraRepository;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Name;

final class WpdbExtraRepository implements ExtraRepository
{
    private readonly CatalogTable $table;

    public function __construct(private readonly Db $db, Clock $clock)
    {
        $this->table = new CatalogTable($db, $clock, 'extras', 'id');
    }

    public function find(int $id): ?Extra
    {
        $row = $this->table->find($id);

        return null === $row ? null : self::fromRow($row);
    }

    /**
     * @return list<Extra>
     */
    public function page(int $offset, int $limit): array
    {
        return \array_map(self::fromRow(...), $this->table->page($offset, $limit));
    }

    public function count(): int
    {
        return $this->table->count();
    }

    /**
     * @return list<Extra>
     */
    public function ofService(int $serviceId): array
    {
        return \array_map(
            static fn (array $row): Extra => self::fromRow(new Row($row)),
            $this->db->getResults(
                'SELECT * FROM %i WHERE (service_id = %d OR service_id IS NULL) AND deleted_at IS NULL ORDER BY id',
                $this->table->table(),
                $serviceId
            )
        );
    }

    public function save(Extra $extra): Extra
    {
        $columns = [
            'service_id' => $extra->serviceId,
            'name' => $extra->name->value,
            'price' => $extra->price->amount,
            'duration_min' => $extra->durationMin,
            'max_qty' => $extra->maxQty,
            'status' => $extra->status->value,
        ];
        $id = $extra->id;
        if (null === $id) {
            $id = $this->table->insert($columns);
        } else {
            $this->table->update($id, $columns);
        }

        return $this->find($id) ?? throw new \LogicException('The extra just saved is gone.');
    }

    public function delete(int $id): void
    {
        $this->table->delete($id);
    }

    public function deleteOfService(int $serviceId): void
    {
        $now = $this->table->now();
        $this->db->update(
            $this->table->table(),
            ['deleted_at' => $now, 'updated_at' => $now],
            ['service_id' => $serviceId, 'deleted_at' => null]
        );
        $this->table->changed();
    }

    private static function fromRow(Row $row): Extra
    {
        return new Extra(
            $row->int('id'),
            Name::fromInput($row->string('name')),
            Money::ofRial($row->int('price')),
            $row->int('duration_min'),
            $row->intOrNull('service_id'),
            $row->int('max_qty'),
            Status::from($row->string('status')),
        );
    }
}
