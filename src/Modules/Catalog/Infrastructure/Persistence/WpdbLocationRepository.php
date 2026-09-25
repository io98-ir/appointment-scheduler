<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Catalog\Domain\Location;
use Vaqtyar\Modules\Catalog\Domain\LocationRepository;
use Vaqtyar\Modules\Catalog\Domain\Name;
use Vaqtyar\Modules\Catalog\Domain\Slug;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\PhoneNumber;

final class WpdbLocationRepository implements LocationRepository
{
    private readonly CatalogTable $table;

    public function __construct(private readonly Db $db, Clock $clock)
    {
        $this->table = new CatalogTable($db, $clock, 'locations', 'sort, id');
    }

    public function find(int $id): ?Location
    {
        $row = $this->table->find($id);

        return null === $row ? null : self::fromRow($row);
    }

    /**
     * @return list<Location>
     */
    public function page(int $offset, int $limit): array
    {
        return \array_map(self::fromRow(...), $this->table->page($offset, $limit));
    }

    public function count(): int
    {
        return $this->table->count();
    }

    public function save(Location $location): Location
    {
        $columns = [
            'name' => $location->name->value,
            'timezone' => $location->timezone->getName(),
            'address' => $location->address,
            'phone' => $location->phone?->e164,
            'holiday_calendar' => $location->holidayCalendar?->value,
            'status' => $location->status->value,
            'sort' => $location->sort,
        ];
        $id = $location->id;
        if (null === $id) {
            $id = $this->table->insert($columns);
        } else {
            $this->table->update($id, $columns);
        }

        return $this->find($id) ?? throw new \LogicException('The location just saved is gone.');
    }

    public function delete(int $id): void
    {
        $this->table->delete($id);
    }

    public function isReferenced(int $id): bool
    {
        return '1' === $this->db->getVar(
            'SELECT EXISTS (SELECT 1 FROM %i WHERE location_id = %d AND deleted_at IS NULL)
                OR EXISTS (SELECT 1 FROM %i WHERE location_id = %d AND deleted_at IS NULL)',
            Tables::name('staff'),
            $id,
            Tables::name('resources'),
            $id
        );
    }

    private static function fromRow(Row $row): Location
    {
        $phone = $row->stringOrNull('phone');
        $calendar = $row->stringOrNull('holiday_calendar');

        return new Location(
            $row->int('id'),
            Name::fromInput($row->string('name')),
            new \DateTimeZone($row->string('timezone')),
            $row->string('address'),
            null === $phone ? null : PhoneNumber::fromInput($phone),
            null === $calendar ? null : Slug::fromInput($calendar),
            Status::from($row->string('status')),
            $row->int('sort'),
        );
    }
}
