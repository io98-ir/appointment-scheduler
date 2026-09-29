<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Infrastructure;

/**
 * What WooCommerce needs from us, and what we need from it, as hooks. All of
 * it is inert on a site without WooCommerce, whose hooks never fire.
 */
final class WooCommerceHooks
{
    /**
     * @param string $pluginFile the main plugin file, for the HPOS declaration.
     * @param \Closure(string): void $settle Settles the payment of an order id; ignores an order that is not ours.
     */
    public function __construct(private readonly string $pluginFile, private readonly \Closure $settle)
    {
    }

    public function register(): void
    {
        // Declared unconditionally: the plugin only uses the order CRUD, so it is compatible with HPOS.
        \add_action('before_woocommerce_init', function (): void {
            if (\class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                    'custom_order_tables',
                    $this->pluginFile,
                    true
                );
            }
        });
        // Any status change may be the payment (or its cancellation), whichever gateway or admin did it.
        \add_action('woocommerce_order_status_changed', function (mixed $orderId): void {
            if (\is_int($orderId) || \is_string($orderId)) {
                ($this->settle)((string) $orderId);
            }
        });
        // A customer done with the order is sent to the callback, which sends them on to the booking page.
        // A payment plugin asks for the first of these, the order's own thank-you link is the second.
        foreach (['woocommerce_get_return_url', 'woocommerce_get_checkout_order_received_url'] as $filter) {
            \add_filter($filter, static function (mixed $url, mixed $order): mixed {
                if (!$order instanceof \WC_Order) {
                    return $url;
                }
                $callback = WcOrderStore::callbackOf($order);

                return '' === $callback ? $url : \add_query_arg('order', (string) $order->get_id(), $callback);
            }, 10, 2);
        }
    }
}
