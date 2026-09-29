<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Contracts;

/**
 * The names other modules print for catalog items, e.g. in a notification.
 * Inactive items are named too; null when the item is deleted or never
 * existed, and the caller prints nothing for it.
 */
interface CatalogNames
{
    public function serviceName(int $serviceId): ?string;

    public function locationName(int $locationId): ?string;

    /**
     * @return ?array{name: string, email: ?string}
     */
    public function staffContact(int $staffId): ?array;
}
