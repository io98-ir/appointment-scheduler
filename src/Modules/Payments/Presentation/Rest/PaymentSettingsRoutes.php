<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Payments\Application\PaymentSettingsService;

/**
 * The gateway settings API (docs/api.md):
 *
 *     GET /payments/settings   which gateways are set up, and the WooCommerce switch
 *     PUT /payments/settings   change merchant ids (in "secrets") and the switch
 *
 * Each needs the payments capability, which PaymentSettingsService checks
 * again. A merchant id is never returned, only whether it is set.
 */
final class PaymentSettingsRoutes
{
    public function __construct(private readonly Router $router, private readonly PaymentSettingsService $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(PaymentSettingsService::CAPABILITY));

        $this->router->add('/payments/settings', 'GET', fn (): array => $this->service->overview(), $allowed);
        $this->router->add(
            '/payments/settings',
            'PUT',
            function (\WP_REST_Request $request): array {
                $p = $request->get_params();
                $secrets = [];
                foreach (\is_array($p['secrets'] ?? null) ? $p['secrets'] : [] as $name => $value) {
                    if (\is_string($name) && \is_string($value)) {
                        $secrets[$name] = \trim($value);
                    }
                }
                $woocommerce = $p['woocommerce'] ?? null;
                $this->service->update($secrets, \is_bool($woocommerce) ? $woocommerce : null);

                return $this->service->overview();
            },
            $allowed,
            [
                'secrets' => ['type' => 'object', 'default' => []],
                'woocommerce' => ['type' => 'boolean'],
            ]
        );
    }
}
