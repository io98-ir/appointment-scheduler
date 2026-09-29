<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Payments\Application\GatewayRegistry;
use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Modules\Payments\Application\PaymentRepository;
use Vaqtyar\Modules\Payments\Application\PaymentService;
use Vaqtyar\Modules\Payments\Domain\Payment;
use Vaqtyar\Modules\Payments\Infrastructure\Migrations\CreatePaymentTables;
use Vaqtyar\Modules\Payments\Infrastructure\OfflineGateway;
use Vaqtyar\Modules\Payments\Infrastructure\Persistence\WpdbPaymentRepository;
use Vaqtyar\Modules\Payments\Presentation\Rest\PaymentRoutes;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Shared\WpAuthorizer;

/**
 * Payments (architecture §3): the gateway Port and its registry, the
 * start-callback-verify flow, and the payments and refunds tables. Booking
 * and Payments meet only through events: this module fires
 * `{prefix}/payments/succeeded` with the appointment and payment ids, and
 * listens to nothing of Booking's. Real gateways (Zarinpal, Zibal, WooCommerce)
 * are added to the registry by T5.2 and T5.3, on the
 * `{prefix}/payments/gateways` filter.
 */
final class PaymentsModule implements Module
{
    public function id(): string
    {
        return 'payments';
    }

    public function register(Container $container): void
    {
        $container->singleton(
            PaymentRepository::class,
            static fn (Container $c) => new WpdbPaymentRepository($c->get(Db::class))
        );
        $container->singleton(GatewayRegistry::class, static function (): GatewayRegistry {
            $gateways = \apply_filters(Hooks::name('payments/gateways'), [new OfflineGateway()]);

            return new GatewayRegistry(\is_array($gateways) ? \array_values(\array_filter(
                $gateways,
                static fn (mixed $gateway): bool => $gateway instanceof PaymentGateway
            )) : []);
        });
        $container->singleton(PaymentService::class, static fn (Container $c) => new PaymentService(
            $c->get(GatewayRegistry::class),
            $c->get(PaymentRepository::class),
            $c->get(TransactionRunner::class),
            $c->get(Clock::class),
            new WpAuthorizer(),
            static function (Payment $payment): void {
                \do_action(Hooks::name('payments/succeeded'), $payment->appointmentId, $payment->id);
            }
        ));
    }

    /**
     * @return list<\Vaqtyar\Kernel\Database\Migration>
     */
    public function migrations(): array
    {
        return [new CreatePaymentTables()];
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
        $container = $context->container;
        \add_action('rest_api_init', static function () use ($container): void {
            (new PaymentRoutes(
                $container->get(Router::class),
                static fn (): PaymentService => $container->get(PaymentService::class)
            ))->register();
        });
    }
}
