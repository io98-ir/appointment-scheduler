<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Contracts;

/**
 * Customers for other modules' lists (architecture §1: a read side with
 * direct SQL). No capability is checked: the callers are admin use cases
 * that do their own authorization.
 */
interface CustomerDirectory
{
    /**
     * @param list<int> $ids
     * @return array<int, CustomerSummary> By id, deleted customers included; unknown ids are left out.
     */
    public function summaries(array $ids): array;

    /**
     * The newest customers whose name, email or phone contains the query,
     * as the admin customer search finds them; deleted ones are left out.
     *
     * @param string $query as typed; normalized here.
     * @return list<int>
     */
    public function matching(string $query, int $limit): array;
}
