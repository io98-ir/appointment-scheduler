<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Infrastructure;

use Vaqtyar\Kernel\Options;
use Vaqtyar\Modules\Payments\Application\GatewayException;
use Vaqtyar\Modules\Payments\Application\StartedAttempt;
use Vaqtyar\Modules\Payments\Application\WcOrderState;
use Vaqtyar\Modules\Payments\Application\WcOrders;

/**
 * WcOrders over WooCommerce's order CRUD, never its posts or tables, so it
 * works with HPOS and with the legacy storage alike. The booking is one fee
 * line; the order remembers the appointment and the callback in hidden meta,
 * which is also how an order of ours is told from any other.
 */
final class WcOrderStore implements WcOrders
{
    private const CLOSED = ['cancelled', 'failed', 'refunded', 'trash'];

    public static function appointmentKey(): string
    {
        return '_' . Options::key('appointment_id');
    }

    public static function callbackKey(): string
    {
        return '_' . Options::key('callback_url');
    }

    /**
     * Where a customer of this order is sent when done; empty for an order that is not ours.
     */
    public static function callbackOf(\WC_Order $order): string
    {
        $callback = $order->get_meta(self::callbackKey());

        return \is_string($callback) ? $callback : '';
    }

    public function currency(): string
    {
        return \get_woocommerce_currency();
    }

    public function create(int $appointmentId, int $total, string $callbackUrl): StartedAttempt
    {
        $order = \wc_create_order(['status' => 'pending']);
        if (!$order instanceof \WC_Order) {
            throw new GatewayException('WooCommerce could not create the order.');
        }
        $fee = new \WC_Order_Item_Fee();
        $fee->set_name(\sprintf(
            /* translators: %d: the appointment number. */
            \__('Appointment %d', 'vaqtyar'),
            $appointmentId
        ));
        $fee->set_amount((string) $total);
        $fee->set_total((string) $total);
        $fee->set_tax_status('none');
        $order->add_item($fee);
        $order->calculate_totals(false);
        $order->update_meta_data(self::appointmentKey(), (string) $appointmentId);
        $order->update_meta_data(self::callbackKey(), $callbackUrl);
        $order->save();

        return new StartedAttempt((string) $order->get_id(), $order->get_checkout_payment_url());
    }

    public function find(string $orderId): ?WcOrderState
    {
        $order = \ctype_digit($orderId) ? \wc_get_order((int) $orderId) : false;
        if (!$order instanceof \WC_Order || '' === self::callbackOf($order)) {
            return null;
        }
        $status = $order->is_paid()
            ? WcOrderState::PAID
            : (\in_array($order->get_status(), self::CLOSED, true) ? WcOrderState::CLOSED : WcOrderState::AWAITING);

        return new WcOrderState($status, (int) \round((float) $order->get_total()), $order->get_transaction_id());
    }
}
