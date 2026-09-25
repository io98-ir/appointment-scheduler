<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Presentation\Rest;

use Vaqtyar\Modules\Catalog\Application\CatalogService;

/**
 * The admin catalog API (docs/api.md): /locations, /staff, /resources,
 * /service-categories, /services and /extras. Registered on rest_api_init.
 */
final class CatalogRoutes
{
    public function __construct(
        private readonly CrudRoutes $routes,
        private readonly CatalogService $catalog,
    ) {
    }

    public function register(): void
    {
        $c = $this->catalog;

        $location = new LocationJson();
        $this->routes->register(
            '/locations',
            $location->fields(),
            $c->locations(...),
            $c->location(...),
            static fn (Input $in, ?int $id) => $c->saveLocation($location->fromInput($in, $id)),
            $c->deleteLocation(...),
            $location->toJson(...)
        );

        $staff = new StaffJson();
        $this->routes->register(
            '/staff',
            $staff->fields(),
            $c->staff(...),
            $c->staffMember(...),
            static fn (Input $in, ?int $id) => $c->saveStaff($staff->fromInput($in, $id)),
            $c->deleteStaff(...),
            $staff->toJson(...)
        );

        $resource = new ResourceJson();
        $this->routes->register(
            '/resources',
            $resource->fields(),
            $c->resources(...),
            $c->resource(...),
            static fn (Input $in, ?int $id) => $c->saveResource($resource->fromInput($in, $id)),
            $c->deleteResource(...),
            $resource->toJson(...)
        );

        $category = new CategoryJson();
        $this->routes->register(
            '/service-categories',
            $category->fields(),
            $c->categories(...),
            $c->category(...),
            static fn (Input $in, ?int $id) => $c->saveCategory($category->fromInput($in, $id)),
            $c->deleteCategory(...),
            $category->toJson(...)
        );

        $service = new ServiceJson();
        $this->routes->register(
            '/services',
            $service->fields(),
            $c->services(...),
            $c->service(...),
            static fn (Input $in, ?int $id) => $c->saveService($service->fromInput($in, $id)),
            $c->deleteService(...),
            $service->toJson(...)
        );

        $extra = new ExtraJson();
        $this->routes->register(
            '/extras',
            $extra->fields(),
            $c->extras(...),
            $c->extra(...),
            static fn (Input $in, ?int $id) => $c->saveExtra($extra->fromInput($in, $id)),
            $c->deleteExtra(...),
            $extra->toJson(...)
        );
    }
}
