<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Infrastructure;

use Vaqtyar\Modules\Payments\Application\GatewayException;
use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Modules\Payments\Application\StartedAttempt;
use Vaqtyar\Modules\Payments\Application\Verification;
use Vaqtyar\Modules\Payments\Application\WcOrderState;
use Vaqtyar\Modules\Payments\Application\WcOrders;
use Vaqtyar\Shared\Domain\Money;

/**
 * WooCommerce as one gateway (ADR-012): a booking is paid through a WooCommerce
 * order, so every payment method the shop has works. Our payments table stays
 * the truth. The authority is the order id; the customer comes back from the
 * order's thank-you page with `order` in the query, and WooCommerceHooks also
 * settles on every order status change, so a payment made after the customer
 * left is not lost.
 *
 * Only shops priced in rials (IRR) or tomans (IRT) can be used, as the
 * amount must convert exactly.
 */
final class WooCommerceGateway implements PaymentGateway
{
    public const ID = 'woocommerce';

    public function __construct(private readonly WcOrders $orders)
    {
    }

    public function id(): string
    {
        return self::ID;
    }

    /**
     * @param array<string, string> $callbackParams
     */
    public function callbackAuthority(array $callbackParams): ?string
    {
        $order = $callbackParams['order'] ?? '';

        return '' === $order ? null : $order;
    }

    public function start(int $appointmentId, Money $amount, string $callbackUrl): StartedAttempt
    {
        return $this->orders->create($appointmentId, $this->toStore($amount), $callbackUrl);
    }

    /**
     * @param array<string, string> $callbackParams
     */
    public function verify(string $authority, Money $amount, array $callbackParams): Verification
    {
        $state = $this->orders->find($authority);
        if (null === $state || WcOrderState::CLOSED === $state->status) {
            return new Verification(false);
        }
        if (WcOrderState::AWAITING === $state->status) {
            // The customer may still pay, so this is not a verdict: it stays awaiting.
            throw new GatewayException('The WooCommerce order is not paid yet.');
        }
        if ($state->total !== $this->toStore($amount)) {
            // Paid, but not what we asked: a person must look, so it is not settled either way.
            throw new GatewayException('The WooCommerce order total is not the payment amount.');
        }
        $reference = $state->transactionId;

        return new Verification(true, '' === (string) $reference ? $authority : $reference);
    }

    /**
     * @throws GatewayException for a currency rials do not convert to, or a fraction of a toman.
     */
    private function toStore(Money $amount): int
    {
        $currency = $this->orders->currency();
        if ('IRR' === $currency) {
            return $amount->amount;
        }
        if ('IRT' === $currency && 0 === $amount->amount % 10) {
            return \intdiv($amount->amount, 10);
        }

        throw new GatewayException('The store currency ' . $currency . ' cannot take this amount.');
    }
}
