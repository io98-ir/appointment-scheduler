<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Payments\Application\LedgerEntry;
use Vaqtyar\Modules\Payments\Application\PaymentLedger;
use Vaqtyar\Modules\Payments\Application\PaymentService;
use Vaqtyar\Modules\Payments\Application\RefundService;
use Vaqtyar\Modules\Payments\Domain\Payment;
use Vaqtyar\Modules\Payments\Domain\PaymentStatus;
use Vaqtyar\Shared\Domain\Money;

/**
 * The payment API (docs/api.md):
 *
 *     GET  /payments/callback/{gateway}   public: where a gateway sends the customer back
 *     GET  /payments?appointment_id=      staff: the payments of an appointment, with what was refunded
 *     POST /payments/offline              staff: money received outside any gateway, recorded as paid
 *     POST /payments/offline/confirm      staff: an offline payment was received
 *     POST /payments/refunds              staff: a refund made by hand, recorded
 *
 * The callback answers with the payment's status only. A customer's browser
 * comes with a `return` page of this site (callbackUrl()) and is sent back
 * there with the outcome in a query parameter, for the widget to show. The
 * gateway's own parameters are passed on as text and never trusted: the
 * gateway is asked to verify.
 */
final class PaymentRoutes
{
    private const LIMIT = 60;

    /**
     * @param \Closure(): PaymentService $service Built when a request needs it.
     * @param \Closure(): RefundService $refunds Built when a request needs it.
     * @param \Closure(): PaymentLedger $ledger Built when a request needs it.
     */
    public function __construct(
        private readonly Router $router,
        private readonly \Closure $service,
        private readonly \Closure $refunds,
        private readonly \Closure $ledger,
    ) {
    }

    public function register(): void
    {
        $this->router->add(
            '/payments/callback/(?P<gateway>[a-z][a-z0-9_]{0,31})',
            'GET',
            fn (\WP_REST_Request $request): \WP_REST_Response|array => $this->callback($request),
            Router::ANYONE,
            [],
            new RateLimit(self::LIMIT, 60)
        );
        $staff = static fn (): bool => \current_user_can(Caps::name('manage_bookings'));
        $this->router->add(
            '/payments',
            'GET',
            function (\WP_REST_Request $request): array {
                $appointmentId = self::int($request->get_param('appointment_id'));
                $ledger = ($this->ledger)();
                $totals = $ledger->totals($appointmentId);

                return [
                    'items' => \array_map(self::entry(...), $ledger->entries($appointmentId)),
                    'paid' => $totals->paid,
                    'refunded' => $totals->refunded,
                ];
            },
            $staff,
            ['appointment_id' => ['type' => 'integer', 'minimum' => 1, 'required' => true]]
        );
        $this->router->add(
            '/payments/offline',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => new \WP_REST_Response(
                self::json(($this->service)()->recordOffline(
                    self::int($request->get_param('appointment_id')),
                    Money::ofRial(self::int($request->get_param('amount')))
                )),
                201
            ),
            $staff,
            [
                'appointment_id' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
                'amount' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
            ]
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
        $this->router->add(
            '/payments/refunds',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => new \WP_REST_Response(
                ['id' => ($this->refunds)()->record(
                    self::int($request->get_param('payment_id')),
                    Money::ofRial(self::int($request->get_param('amount'))),
                    \sanitize_textarea_field(self::string($request->get_param('reason'))),
                    \get_current_user_id()
                )],
                201
            ),
            static fn (): bool => \current_user_can(Caps::name('manage_bookings')),
            [
                'payment_id' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
                'amount' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
                'reason' => ['type' => 'string', 'maxLength' => 2000, 'default' => ''],
            ]
        );
    }

    /**
     * The url a gateway is given to send the customer back to, with "{gateway}" for PaymentService to fill in.
     */
    public static function callbackUrl(string $returnUrl): string
    {
        $base = \rest_url(Identity::REST_NAMESPACE . '/payments/callback/{gateway}');

        return $base . (\str_contains($base, '?') ? '&' : '?') . 'return=' . \rawurlencode($returnUrl);
    }

    /**
     * The query parameter that carries the outcome back to the page the customer came from.
     */
    public static function outcomeParam(): string
    {
        return Identity::PREFIX . '_payment';
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     * @return \WP_REST_Response|array<string, mixed>
     */
    private function callback(\WP_REST_Request $request): \WP_REST_Response|array
    {
        $payment = ($this->service)()->settleCallback(
            self::string($request->get_url_params()['gateway'] ?? null),
            self::params($request->get_query_params())
        );
        $return = self::string($request->get_param('return'));
        if ('' === $return) {
            return self::json($payment);
        }
        $outcome = match ($payment->status) {
            PaymentStatus::Succeeded => 'succeeded',
            PaymentStatus::Failed => 'failed',
            default => 'pending',
        };
        $response = new \WP_REST_Response(null, 302);
        $response->header(
            'Location',
            \add_query_arg(self::outcomeParam(), $outcome, \wp_validate_redirect($return, \home_url('/')))
        );

        return $response;
    }

    private static function int(mixed $value): int
    {
        return \is_int($value) || \is_numeric($value) ? (int) $value : 0;
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
     * @return array<string, mixed>
     */
    private static function entry(LedgerEntry $entry): array
    {
        return self::json($entry->payment) + ['refunded' => $entry->refunded];
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
