<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

use Vaqtyar\Modules\Payments\Domain\Payment;
use Vaqtyar\Modules\Payments\Domain\PaymentStatus;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\TransactionRunner;

/**
 * The payment flow (booking-engine §7): start on the first gateway that
 * answers (failover), then settle from its callback. Settling is idempotent:
 * the gateway is asked outside any lock, and the payment moves once, under a
 * row lock, so a repeated or parallel callback changes nothing and fires
 * the success event once.
 */
final class PaymentService
{
    public const OFFLINE = 'offline';
    private const OFFLINE_CAPABILITY = 'manage_bookings';

    /**
     * @param \Closure(Payment): void $onSucceeded Called once, after the commit, for a payment that went through.
     */
    public function __construct(
        private readonly GatewayRegistry $gateways,
        private readonly PaymentRepository $payments,
        private readonly TransactionRunner $transaction,
        private readonly Clock $clock,
        private readonly Authorizer $authorizer,
        private readonly \Closure $onSucceeded,
    ) {
    }

    /**
     * @param string $callbackUrl where a gateway sends the customer back; "{gateway}" in it becomes the gateway's id.
     * @param list<string> $preferred gateway ids to try first, in order; the rest follow.
     * @param bool $onlineOnly skip the offline gateway: the customer pays now, at a gateway's page.
     * @throws InvalidValue invalid_amount for an amount that is not positive.
     * @throws Conflict no_gateway_available when every gateway refuses.
     */
    public function start(
        int $appointmentId,
        Money $amount,
        string $callbackUrl,
        array $preferred = [],
        bool $onlineOnly = false,
    ): StartedPayment {
        if ($amount->isNegative() || $amount->isZero()) {
            throw new InvalidValue('invalid_amount', 'A payment is a positive amount.');
        }
        foreach ($this->ordered($preferred) as $gateway) {
            if ($onlineOnly && self::OFFLINE === $gateway->id()) {
                continue;
            }
            try {
                $attempt = $gateway->start(
                    $appointmentId,
                    $amount,
                    \str_replace('{gateway}', $gateway->id(), $callbackUrl)
                );
            } catch (GatewayException) {
                continue;
            }
            $payment = $this->payments->add(
                new Payment(
                    null,
                    $appointmentId,
                    $gateway->id(),
                    $amount,
                    PaymentStatus::AwaitingCallback,
                    $attempt->authority
                ),
                $this->now()
            );

            return new StartedPayment($payment, $attempt->redirectUrl);
        }

        throw new Conflict('no_gateway_available', 'No payment gateway could take the payment.');
    }

    /**
     * Settles a payment from its gateway's callback.
     *
     * @param array<string, string> $params what the gateway sent back.
     * @throws NotFound payment_not_found for an unknown gateway or authority.
     */
    public function settle(string $gatewayId, string $authority, array $params): Payment
    {
        $gateway = $this->gateways->get($gatewayId);
        $payment = null === $gateway ? null : $this->payments->find($gatewayId, $authority);
        if (null === $gateway || null === $payment) {
            throw new NotFound('payment_not_found', 'There is no such payment.');
        }
        if ($payment->isFinal()) {
            return $payment;
        }
        try {
            $verdict = $gateway->verify($authority, $payment->amount, $params);
        } catch (GatewayException) {
            // Unreachable now: it stays awaiting for the reconciliation job (T5.2) or the next callback.
            return $payment;
        }

        return $this->apply($payment, $verdict);
    }

    /**
     * Settles a payment from a gateway's callback, whose own parameter names it.
     *
     * @param array<string, string> $params
     * @throws NotFound payment_not_found
     */
    public function settleCallback(string $gatewayId, array $params): Payment
    {
        $authority = $this->gateways->get($gatewayId)?->callbackAuthority($params);
        if (null === $authority) {
            throw new NotFound('payment_not_found', 'There is no such payment.');
        }

        return $this->settle($gatewayId, $authority, $params);
    }

    /**
     * The reconciliation job (booking-engine §7): asks the gateway about
     * payments whose customer never came back, so a paid one is not lost.
     *
     * @return int how many it settled, paid or not.
     */
    public function reconcile(int $olderThanSeconds, int $limit): int
    {
        $settled = 0;
        foreach ($this->payments->awaitingBefore($this->now() - $olderThanSeconds, $limit) as $payment) {
            try {
                $settled += $this->settle($payment->gateway, $payment->authority, [])->isFinal() ? 1 : 0;
            } catch (NotFound) {
                // Its gateway was switched off: nothing to ask.
            }
        }

        return $settled;
    }

    /**
     * Staff record that an offline payment was received.
     *
     * @throws Forbidden without the booking capability.
     * @throws NotFound payment_not_found
     */
    public function confirmOffline(string $authority): Payment
    {
        if (!$this->authorizer->allows(self::OFFLINE_CAPABILITY)) {
            throw new Forbidden(self::OFFLINE_CAPABILITY);
        }
        $payment = $this->payments->find(self::OFFLINE, $authority);
        if (null === $payment) {
            throw new NotFound('payment_not_found', 'There is no such payment.');
        }

        return $payment->isFinal() ? $payment : $this->apply($payment, new Verification(true));
    }

    private function apply(Payment $seen, Verification $verdict): Payment
    {
        $fired = null;
        $settled = $this->transaction->run(function () use ($seen, $verdict, &$fired): Payment {
            $locked = $this->payments->find($seen->gateway, $seen->authority, true) ?? $seen;
            if ($locked->isFinal()) {
                return $locked;
            }
            $next = $verdict->paid ? $locked->succeeded($verdict->refId, $verdict->cardMask) : $locked->failed();
            if ($this->payments->settle($next, $this->now())) {
                $fired = $verdict->paid ? $next : null;

                return $next;
            }

            return $this->payments->find($seen->gateway, $seen->authority) ?? $locked;
        });
        if (null !== $fired) {
            ($this->onSucceeded)($fired);
        }

        return $settled;
    }

    /**
     * @param list<string> $preferred
     * @return list<PaymentGateway>
     */
    private function ordered(array $preferred): array
    {
        $first = [];
        foreach ($preferred as $id) {
            $gateway = $this->gateways->get($id);
            if (null !== $gateway) {
                $first[$id] = $gateway;
            }
        }
        foreach ($this->gateways->all() as $gateway) {
            $first[$gateway->id()] ??= $gateway;
        }

        return \array_values($first);
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
