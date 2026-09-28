<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Infrastructure\Query;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Customers\Contracts\CustomerDirectory;
use Vaqtyar\Modules\Customers\Contracts\CustomerSummary;
use Vaqtyar\Modules\Customers\Infrastructure\Persistence\WpdbCustomerRepository;
use Vaqtyar\Shared\Domain\SearchText;

/**
 * CustomerDirectory on the customers table: one query per call, whatever
 * the number of ids.
 */
final class WpdbCustomerDirectory implements CustomerDirectory
{
    public function __construct(private readonly Db $db)
    {
    }

    /**
     * @param list<int> $ids
     * @return array<int, CustomerSummary>
     */
    public function summaries(array $ids): array
    {
        $ids = \array_values(\array_unique($ids));
        if ([] === $ids) {
            return [];
        }
        $rows = $this->db->getResults(
            'SELECT id, first_name, last_name, phone, deleted_at FROM %i WHERE id IN ('
                . \implode(',', \array_fill(0, \count($ids), '%d')) . ')',
            Tables::name('customers'),
            ...$ids
        );
        $summaries = [];
        foreach ($rows as $values) {
            $row = new Row($values);
            $id = $row->int('id');
            $summaries[$id] = new CustomerSummary(
                $id,
                \trim($row->string('first_name') . ' ' . $row->string('last_name')),
                $row->stringOrNull('phone'),
                null !== $row->stringOrNull('deleted_at')
            );
        }

        return $summaries;
    }

    /**
     * @return list<int>
     */
    public function matching(string $query, int $limit): array
    {
        $query = SearchText::normalize($query);
        if ('' === $query) {
            return [];
        }
        [$where, $args] = WpdbCustomerRepository::where($query, null);
        $rows = $this->db->getResults(
            'SELECT id FROM %i WHERE ' . $where . ' ORDER BY id DESC LIMIT %d',
            Tables::name('customers'),
            ...[...$args, $limit]
        );

        return \array_map(static fn (array $row): int => (new Row($row))->int('id'), $rows);
    }
}
