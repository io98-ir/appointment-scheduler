<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Payments\Application\PaymentService;
use Vaqtyar\Modules\Payments\Domain\Payment;

/**
 * The payment API (docs/api.md):
 *
 *     GET  /payments/callback/{gateway}   public: where a gateway sends the customer back
 *     POST /payments/offline/confirm      staff: an offline payment was received
 *
 * The callback answers with the payment's status only; the customer-facing
 * page (T4.2's success screen) is the caller's. The gateway's own parameters
 * are passed on as text and never trusted: the gateway is asked to verify.
 */
final class PaymentRoutes
{
    private const LIMIT = 60;

    /**
     * @param \Closure(): PaymentService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $this->router->add(
            '/payments/callback/(?P<gateway>[a-z][a-z0-9_]{0,31})',
            'GET',
            fn (\WP_REST_Request $request): array => self::json(($this->service)()->settle(
                self::string($request->get_url_params()['gateway'] ?? null),
                self::string($request->get_param('authority')),
                self::params($request->get_query_params())
            )),
            Router::ANYONE,
            ['authority' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 191, 'required' => true]],
            new RateLimit(self::LIMIT, 60)
        );
        $this->router->add(
            '/payments/offline/confirm',
            'POST',
            fn (\WP_REST_Request $request): array => self::json(
                ($this->service)()->confirmOffline(self::string($request->get_param('authority')))
            ),
            static fn (): bool => \current_user_can(Caps::name('manage_bookings')),
            ['authority' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 191, 'required' => true]]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'appointment_id' => $payment->appointmentId,
            'gateway' => $payment->gateway,
            'amount' => $payment->amount->toArray(),
            'status' => $payment->status->value,
            'ref_id' => $payment->refId,
        ];
    }

    /**
     * @param array<mixed> $query
     * @return array<string, string>
     */
    private static function params(array $query): array
    {
        $params = [];
        foreach ($query as $key => $value) {
            if (\is_string($value)) {
                $params[(string) $key] = $value;
            }
        }

        return $params;
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
