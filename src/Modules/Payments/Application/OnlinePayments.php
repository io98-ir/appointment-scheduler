<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

use Vaqtyar\Modules\Payments\Contracts\PaymentsApi;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Money;

/**
 * PaymentsApi over the payment flow: every gateway but the offline one.
 */
final class OnlinePayments implements PaymentsApi
{
    /**
     * @param \Closure(string): string $callbackFor The callback url for a return url,
     *     with "{gateway}" where the gateway's id goes.
     */
    public function __construct(
        private readonly PaymentService $payments,
        private readonly GatewayRegistry $gateways,
        private readonly \Closure $callbackFor,
    ) {
    }

    public function onlineAvailable(): bool
    {
        foreach ($this->gateways->all() as $gateway) {
            if (PaymentService::OFFLINE !== $gateway->id()) {
                return true;
            }
        }

        return false;
    }

    public function startOnline(int $appointmentId, Money $amount, string $returnUrl): string
    {
        $started = $this->payments->start($appointmentId, $amount, ($this->callbackFor)($returnUrl), [], true);

        return $started->redirectUrl
            ?? throw new Conflict('no_gateway_available', 'The gateway gave no page to pay at.');
    }
}
