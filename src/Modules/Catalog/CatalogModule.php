<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Catalog\Application\CatalogReader;
use Vaqtyar\Modules\Catalog\Application\CatalogService;
use Vaqtyar\Modules\Catalog\Application\PublicMenu;
use Vaqtyar\Modules\Catalog\Application\CatalogNameReader;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Catalog\Contracts\CatalogNames;
use Vaqtyar\Modules\Catalog\Domain\BookableResourceRepository;
use Vaqtyar\Modules\Catalog\Domain\ExtraRepository;
use Vaqtyar\Modules\Catalog\Domain\LocationRepository;
use Vaqtyar\Modules\Catalog\Domain\ServiceCategoryRepository;
use Vaqtyar\Modules\Catalog\Domain\ServiceRepository;
use Vaqtyar\Modules\Catalog\Domain\StaffRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Migrations\CreateCatalogTables;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbBookableResourceRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbExtraRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbLocationRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbServiceCategoryRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbServiceRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbStaffRepository;
use Vaqtyar\Modules\Catalog\Presentation\Rest\CatalogRoutes;
use Vaqtyar\Modules\Catalog\Presentation\Rest\CrudRoutes;
use Vaqtyar\Modules\Catalog\Presentation\Rest\PublicMenuRoutes;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\WpAuthorizer;

/**
 * Locations, staff, resources and services (architecture §3): the admin
 * REST API over them, and CatalogApi for the other modules.
 */
final class CatalogModule implements Module
{
    public function id(): string
    {
        return 'catalog';
    }

    public function register(Container $container): void
    {
        $container->singleton(
            LocationRepository::class,
            static fn (Container $c) => new WpdbLocationRepository($c->get(Db::class), $c->get(Clock::class))
        );
        $container->singleton(
            StaffRepository::class,
            static fn (Container $c) => new WpdbStaffRepository($c->get(Db::class), $c->get(Clock::class))
        );
        $container->singleton(
            BookableResourceRepository::class,
            static fn (Container $c) => new WpdbBookableResourceRepository($c->get(Db::class), $c->get(Clock::class))
        );
        $container->singleton(
            ServiceCategoryRepository::class,
            static fn (Container $c) => new WpdbServiceCategoryRepository($c->get(Db::class), $c->get(Clock::class))
        );
        $container->singleton(
            ServiceRepository::class,
            static fn (Container $c) => new WpdbServiceRepository(
                $c->get(Db::class),
                $c->get(Transaction::class),
                $c->get(Clock::class)
            )
        );
        $container->singleton(
            ExtraRepository::class,
            static fn (Container $c) => new WpdbExtraRepository($c->get(Db::class), $c->get(Clock::class))
        );
        $container->singleton(CatalogService::class, static fn (Container $c) => new CatalogService(
            new WpAuthorizer(),
            $c->get(LocationRepository::class),
            $c->get(StaffRepository::class),
            $c->get(BookableResourceRepository::class),
            $c->get(ServiceCategoryRepository::class),
            $c->get(ServiceRepository::class),
            $c->get(ExtraRepository::class)
        ));
        $container->singleton(PublicMenu::class, static fn (Container $c) => new PublicMenu(
            $c->get(ServiceRepository::class),
            $c->get(StaffRepository::class),
            $c->get(LocationRepository::class),
            $c->get(ServiceCategoryRepository::class)
        ));
        $container->singleton(CatalogNames::class, static fn (Container $c) => new CatalogNameReader(
            $c->get(ServiceRepository::class),
            $c->get(StaffRepository::class),
            $c->get(LocationRepository::class)
        ));
        $container->singleton(CatalogApi::class, static fn (Container $c) => new CatalogReader(
            $c->get(ServiceRepository::class),
            $c->get(StaffRepository::class),
            $c->get(BookableResourceRepository::class),
            $c->get(LocationRepository::class),
            $c->get(ExtraRepository::class)
        ));
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
        return [CatalogService::CAPABILITY => ['administrator']];
    }

    public function boot(Context $context): void
    {
        $container = $context->container;
        \add_action('rest_api_init', static function () use ($container): void {
            (new CatalogRoutes(
                new CrudRoutes($container->get(Router::class)),
                $container->get(CatalogService::class)
            ))->register();
            (new PublicMenuRoutes(
                $container->get(Router::class),
                static fn (): PublicMenu => $container->get(PublicMenu::class)
            ))->register();
        });
    }
}
