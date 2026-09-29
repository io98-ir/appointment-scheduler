<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\SecretStore;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Payments\Application\GatewayRegistry;
use Vaqtyar\Modules\Payments\Application\JsonHttp;
use Vaqtyar\Modules\Payments\Application\OnlinePayments;
use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Modules\Payments\Application\PaymentRepository;
use Vaqtyar\Modules\Payments\Application\PaymentService;
use Vaqtyar\Modules\Payments\Application\PaymentSettingsService;
use Vaqtyar\Modules\Payments\Application\PaymentSettingsStore;
use Vaqtyar\Modules\Payments\Application\RefundRepository;
use Vaqtyar\Modules\Payments\Application\RefundService;
use Vaqtyar\Modules\Payments\Contracts\PaymentsApi;
use Vaqtyar\Modules\Payments\Domain\Payment;
use Vaqtyar\Modules\Payments\Infrastructure\Migrations\CreatePaymentTables;
use Vaqtyar\Modules\Payments\Infrastructure\OfflineGateway;
use Vaqtyar\Modules\Payments\Infrastructure\Persistence\WpdbPaymentRepository;
use Vaqtyar\Modules\Payments\Infrastructure\Persistence\WpdbRefundRepository;
use Vaqtyar\Modules\Payments\Infrastructure\SettingsPaymentStore;
use Vaqtyar\Modules\Payments\Infrastructure\WcOrderStore;
use Vaqtyar\Modules\Payments\Infrastructure\WooCommerceGateway;
use Vaqtyar\Modules\Payments\Infrastructure\WooCommerceHooks;
use Vaqtyar\Modules\Payments\Infrastructure\WooCommerceSettings;
use Vaqtyar\Modules\Payments\Infrastructure\WpJsonHttp;
use Vaqtyar\Modules\Payments\Infrastructure\ZarinpalGateway;
use Vaqtyar\Modules\Payments\Infrastructure\ZibalGateway;
use Vaqtyar\Modules\Payments\Presentation\Rest\PaymentRoutes;
use Vaqtyar\Modules\Payments\Presentation\Rest\PaymentSettingsRoutes;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Shared\WpAuthorizer;

/**
 * Payments (architecture §3): the gateway Port and its registry, the
 * start-callback-verify flow, and the payments and refunds tables. Booking
 * and Payments meet only through events: this module fires
 * `{prefix}/payments/succeeded` and `{prefix}/payments/refunded`, and
 * listens to nothing of Booking's. More gateways are added to the registry on
 * the `{prefix}/payments/gateways` filter. WooCommerce (T5.3) is a gateway
 * too, once WooCommerceSettings turns it on.
 *
 * Zarinpal and Zibal are on when their merchant id is stored as a secret
 * (`zarinpal_merchant`, `zibal_merchant`; a wp-config constant works too).
 * They are set on the settings screen and in the setup wizard (T6.1).
 */
final class PaymentsModule implements Module
{
    private const RECONCILE_EVERY_SECONDS = 300;
    private const RECONCILE_AFTER_SECONDS = 900;
    private const RECONCILE_BATCH = 50;

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
        $container->singleton(
            RefundRepository::class,
            static fn (Container $c) => new WpdbRefundRepository($c->get(Db::class))
        );
        $container->singleton(PaymentSettingsStore::class, static fn (Container $c) => new SettingsPaymentStore(
            $c->get(SecretStore::class),
            $c->get(Settings::class)
        ));
        $container->singleton(PaymentSettingsService::class, static fn (Container $c) => new PaymentSettingsService(
            new WpAuthorizer(),
            $c->get(PaymentSettingsStore::class)
        ));
        $container->singleton(JsonHttp::class, static fn () => new WpJsonHttp());
        $container->singleton(GatewayRegistry::class, static function (Container $c): GatewayRegistry {
            $secrets = $c->get(SecretStore::class);
            $http = $c->get(JsonHttp::class);
            $own = [new OfflineGateway()];
            $zarinpal = $secrets->get('zarinpal_merchant');
            if (null !== $zarinpal) {
                $own[] = new ZarinpalGateway($http, $zarinpal);
            }
            $zibal = $secrets->get('zibal_merchant');
            if (null !== $zibal) {
                $own[] = new ZibalGateway($http, $zibal);
            }
            if (
                \function_exists('wc_create_order')
                && $c->get(Settings::class)->get(WooCommerceSettings::class)->enabled
            ) {
                $own[] = new WooCommerceGateway(new WcOrderStore());
            }
            $gateways = \apply_filters(Hooks::name('payments/gateways'), $own);

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
        $container->singleton(PaymentsApi::class, static fn (Container $c) => new OnlinePayments(
            $c->get(PaymentService::class),
            $c->get(GatewayRegistry::class),
            static fn (string $returnUrl): string => PaymentRoutes::callbackUrl($returnUrl)
        ));
        $container->singleton(RefundService::class, static fn (Container $c) => new RefundService(
            $c->get(PaymentRepository::class),
            $c->get(RefundRepository::class),
            $c->get(TransactionRunner::class),
            $c->get(Clock::class),
            new WpAuthorizer(),
            static function (int $appointmentId, int $refundId): void {
                \do_action(Hooks::name('payments/refunded'), $appointmentId, $refundId);
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
        return [PaymentSettingsService::CAPABILITY => ['administrator']];
    }

    public function boot(Context $context): void
    {
        $container = $context->container;
        $reconcile = Hooks::name('payments/reconcile');
        \add_action($reconcile, static function () use ($container): void {
            $container->get(PaymentService::class)->reconcile(self::RECONCILE_AFTER_SECONDS, self::RECONCILE_BATCH);
        });
        // Checked on admin requests only, which Action Scheduler's own runner is.
        \add_action('admin_init', static function () use ($reconcile): void {
            if (!\as_has_scheduled_action($reconcile)) {
                \as_schedule_recurring_action(\time(), self::RECONCILE_EVERY_SECONDS, $reconcile, [], '', true);
            }
        });
        (new WooCommerceHooks($context->pluginFile, static function (string $orderId) use ($container): void {
            try {
                $container->get(PaymentService::class)->settle(WooCommerceGateway::ID, $orderId, []);
            } catch (NotFound) {
                // Not the payment of a booking: most orders of a shop.
            }
        }))->register();
        \add_action('rest_api_init', static function () use ($container): void {
            (new PaymentRoutes(
                $container->get(Router::class),
                static fn (): PaymentService => $container->get(PaymentService::class),
                static fn (): RefundService => $container->get(RefundService::class)
            ))->register();
            (new PaymentSettingsRoutes(
                $container->get(Router::class),
                $container->get(PaymentSettingsService::class)
            ))->register();
        });
    }
}
