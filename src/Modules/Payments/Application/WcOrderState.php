<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

/**
 * Where a WooCommerce order stands, as far as a payment cares.
 */
final class WcOrderState
{
    public const PAID = 'paid';
    /** Pending or on hold: the customer may still pay. */
    public const AWAITING = 'awaiting';
    /** Cancelled, failed or refunded: it will not be paid. */
    public const CLOSED = 'closed';

    /**
     * @param self::PAID|self::AWAITING|self::CLOSED $status
     * @param int $total in the store's currency.
     */
    public function __construct(
        public readonly string $status,
        public readonly int $total,
        public readonly ?string $transactionId = null,
    ) {
    }
}
