<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

/**
 * The WooCommerce orders a booking is paid through: the one door to WooCommerce
 * (ADR-012), so the gateway's rules run without it in unit tests.
 */
interface WcOrders
{
    /**
     * The store's currency code, e.g. IRR or IRT.
     */
    public function currency(): string;

    /**
     * Opens a pending order for the booking and says where to pay it.
     *
     * @param int $total in the store's currency.
     * @param string $callbackUrl where WooCommerce sends the customer once the order is done.
     * @throws GatewayException when WooCommerce cannot make the order.
     */
    public function create(int $appointmentId, int $total, string $callbackUrl): StartedAttempt;

    /**
     * The state of an order this plugin made; null for any other.
     */
    public function find(string $orderId): ?WcOrderState;
}
