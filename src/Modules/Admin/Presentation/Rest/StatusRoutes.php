<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Admin\Application\StatusService;
use Vaqtyar\Modules\Admin\Presentation\HealthText;

/**
 * The System Status API (docs/api.md):
 *
 *     GET /status                versions, health checks, queue, schema, modules and recent errors
 *     PUT /modules/{id}          {enabled}: turn a switchable module on or off (from the next request)
 *     PUT /data                  {delete_on_uninstall}: whether deleting the plugin deletes its data
 *
 * Each needs the system capability, which StatusService checks again.
 */
final class StatusRoutes
{
    public function __construct(private readonly Router $router, private readonly StatusService $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(StatusService::CAPABILITY));

        $this->router->add('/status', 'GET', function (): array {
            $report = $this->service->report();

            return [
                'versions' => $report['versions'],
                'checks' => \array_map(
                    static fn ($check): array => ['id' => $check->id, 'status' => $check->status->value]
                        + HealthText::of($check),
                    $report['checks']
                ),
                'queue' => $report['queue'],
                'schema' => (object) $report['schema'],
                'modules' => $report['modules'],
                'delete_on_uninstall' => $report['delete_on_uninstall'],
                'errors' => $report['errors'],
            ];
        }, $allowed);

        $this->router->add(
            '/data',
            'PUT',
            fn (\WP_REST_Request $request): array => [
                'delete_on_uninstall' => $this->service->setDeleteOnUninstall(
                    true === $request->get_param('delete_on_uninstall')
                ),
            ],
            $allowed,
            ['delete_on_uninstall' => ['type' => 'boolean', 'required' => true]]
        );

        $this->router->add(
            '/modules/(?P<id>[a-z][a-z0-9_]*)',
            'PUT',
            function (\WP_REST_Request $request): array {
                $id = $request->get_param('id');

                return ['modules' => $this->service->setModuleEnabled(
                    \is_string($id) ? $id : '',
                    true === $request->get_param('enabled')
                )];
            },
            $allowed,
            ['enabled' => ['type' => 'boolean', 'required' => true]]
        );
    }
}
