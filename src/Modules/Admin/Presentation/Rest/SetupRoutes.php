<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Admin\Application\SetupService;
use Vaqtyar\Shared\Domain\Brand;

/**
 * The white-label and onboarding API (docs/api.md):
 *
 *     GET /brand         the owner's name, logo and colour ("" means the default)
 *     PUT /brand         replace them
 *     GET /onboarding    {done}
 *     PUT /onboarding    {done}: finish or skip the wizard (false shows it again)
 *
 * Each needs the admin-app capability, which SetupService checks again.
 */
final class SetupRoutes
{
    public function __construct(private readonly Router $router, private readonly SetupService $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(SetupService::CAPABILITY));

        $this->router->add('/brand', 'GET', fn (): array => self::brand($this->service->brand()), $allowed);
        $this->router->add(
            '/brand',
            'PUT',
            function (\WP_REST_Request $request): array {
                $p = $request->get_params();

                return self::brand($this->service->saveBrand(
                    self::text($p['name'] ?? null),
                    self::text($p['logo_url'] ?? null),
                    self::text($p['color'] ?? null)
                ));
            },
            $allowed,
            [
                'name' => ['type' => 'string', 'default' => ''],
                'logo_url' => ['type' => 'string', 'default' => ''],
                'color' => ['type' => 'string', 'default' => ''],
            ]
        );
        $this->router->add(
            '/onboarding',
            'GET',
            fn (): array => ['done' => $this->service->onboarded()],
            $allowed
        );
        $this->router->add(
            '/onboarding',
            'PUT',
            function (\WP_REST_Request $request): array {
                $done = true === $request->get_param('done');
                $this->service->setOnboarded($done);

                return ['done' => $done];
            },
            $allowed,
            ['done' => ['type' => 'boolean', 'required' => true]]
        );
    }

    /**
     * @return array{name: string, logo_url: string, color: string}
     */
    private static function brand(Brand $brand): array
    {
        return ['name' => $brand->name, 'logo_url' => $brand->logoUrl, 'color' => $brand->color];
    }

    private static function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
