<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Modules\Catalog\Infrastructure\Migrations\CreateCatalogTables;

/**
 * Locations, staff, resources and services (architecture §3). The
 * repositories, the admin REST API, its capabilities and the CatalogApi
 * contract come with T1.2.
 */
final class CatalogModule implements Module
{
    public function id(): string
    {
        return 'catalog';
    }

    public function register(Container $container): void
    {
    }

    /**
     * @return list<\Vaqtyar\Kernel\Database\Migration>
     */
    public function migrations(): array
    {
        return [new CreateCatalogTables()];
    }

    /**
     * @return array<string, list<string>>
     */
    public function capabilities(): array
    {
        return [];
    }

    public function boot(Context $context): void
    {
    }
}
