<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Presentation\Rest;

use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Catalog\Application\PublicMenu;

/**
 * The public booking menu (docs/api.md): GET /catalog, without login, rate
 * limited like GET /availability (120 requests a minute per client).
 */
final class PublicMenuRoutes
{
    private const LIMIT = 120;

    /**
     * @param \Closure(): PublicMenu $menu Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $menu)
    {
    }

    public function register(): void
    {
        $this->router->add(
            '/catalog',
            'GET',
            fn (): array => ($this->menu)()->build(),
            Router::ANYONE,
            [],
            new RateLimit(self::LIMIT, 60)
        );
    }
}
