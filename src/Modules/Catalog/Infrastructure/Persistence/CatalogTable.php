<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Shared\Domain\Clock;

/**
 * The storage every catalog table shares: soft delete (deleted_at), the
 * created_at and updated_at stamps in UTC, and paging in the admin's order.
 * The repositories map their entity to and from its columns.
 */
final class CatalogTable
{
    /**
     * @param string $name The short table name, as for Tables::name().
     * @param 'sort, id'|'id' $orderBy The admin's order; ids break ties so pages do not overlap.
     */
    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly string $name,
        private readonly string $orderBy,
    ) {
    }

    public function find(int $id): ?Row
    {
        $rows = $this->db->getResults(
            'SELECT * FROM %i WHERE id = %d AND deleted_at IS NULL',
            $this->table(),
            $id
        );

        return [] === $rows ? null : new Row($rows[0]);
    }

    /**
     * @return list<Row>
     */
    public function page(int $offset, int $limit): array
    {
        $rows = 'id' === $this->orderBy
            ? $this->db->getResults(
                'SELECT * FROM %i WHERE deleted_at IS NULL ORDER BY id LIMIT %d OFFSET %d',
                $this->table(),
                $limit,
                $offset
            )
            : $this->db->getResults(
                'SELECT * FROM %i WHERE deleted_at IS NULL ORDER BY sort, id LIMIT %d OFFSET %d',
                $this->table(),
                $limit,
                $offset
            );

        return \array_map(static fn (array $row): Row => new Row($row), $rows);
    }

    public function count(): int
    {
        return (int) $this->db->getVar('SELECT COUNT(*) FROM %i WHERE deleted_at IS NULL', $this->table());
    }

    /**
     * @param array<string, int|string|null> $columns
     * @return int The new id.
     */
    public function insert(array $columns): int
    {
        $now = $this->now();

        return $this->db->insert($this->table(), $columns + ['created_at' => $now, 'updated_at' => $now]);
    }

    /**
     * @param array<string, int|string|null> $columns
     */
    public function update(int $id, array $columns): void
    {
        $this->db->update(
            $this->table(),
            $columns + ['updated_at' => $this->now()],
            ['id' => $id, 'deleted_at' => null]
        );
    }

    public function delete(int $id): void
    {
        $now = $this->now();
        $this->db->update(
            $this->table(),
            ['deleted_at' => $now, 'updated_at' => $now],
            ['id' => $id, 'deleted_at' => null]
        );
    }

    /**
     * The full name, read on every call: switch_to_blog() changes the prefix.
     */
    public function table(): string
    {
        return Tables::name($this->name);
    }

    /**
     * The current time as the DATETIME columns store it (UTC, implementation-notes §4).
     */
    public function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
