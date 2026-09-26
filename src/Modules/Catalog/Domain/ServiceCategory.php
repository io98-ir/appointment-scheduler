<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\Name;

/**
 * Groups services in the booking widget and the admin.
 */
final class ServiceCategory
{
    use GuardsStoredNumbers;

    /**
     * @param ?int $id null until stored.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly Name $name,
        public readonly Color $color,
        public readonly int $sort = 0,
    ) {
        self::assertIds($id);
        self::assertSort($sort);
    }
}
