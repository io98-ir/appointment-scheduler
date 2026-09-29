<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

use Vaqtyar\Shared\Domain\Money;

/**
 * A way to pay (booking-engine §7): the Port every gateway adapter
 * implements. Amounts are rials; an adapter converts to its own unit.
 */
interface PaymentGateway
{
    /**
     * Stable, a-z and _, e.g. "zarinpal"; stored with every payment.
     */
    public function id(): string;

    /**
     * Which of the callback's parameters names the payment (each gateway
     * calls it something else); null when there is none.
     *
     * @param array<string, string> $callbackParams
     */
    public function callbackAuthority(array $callbackParams): ?string;

    /**
     * Opens a payment with the gateway.
     *
     * @throws GatewayException when the gateway cannot take it now (the next gateway is tried).
     */
    public function start(int $appointmentId, Money $amount, string $callbackUrl): StartedAttempt;

    /**
     * Asks the gateway whether the payment went through. Called for the
     * same authority more than once; the answer must not change.
     *
     * @param array<string, string> $callbackParams what the gateway sent back, as text.
     * @throws GatewayException when the gateway cannot be reached; the payment stays awaiting.
     */
    public function verify(string $authority, Money $amount, array $callbackParams): Verification;
}
