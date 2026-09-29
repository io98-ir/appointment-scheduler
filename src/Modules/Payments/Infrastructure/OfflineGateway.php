<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Infrastructure;

use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Modules\Payments\Application\PaymentService;
use Vaqtyar\Modules\Payments\Application\StartedAttempt;
use Vaqtyar\Modules\Payments\Application\Verification;
use Vaqtyar\Shared\Domain\Money;

/**
 * Pay at the place, in cash or by card transfer: there is no page to send
 * the customer to, and no callback. Staff record the payment with
 * PaymentService::confirmOffline(), which passes `confirmed` as a real
 * boolean; a callback's parameters are text, so nobody outside can forge it.
 */
final class OfflineGateway implements PaymentGateway
{
    public function id(): string
    {
        return PaymentService::OFFLINE;
    }

    public function start(int $appointmentId, Money $amount, string $callbackUrl): StartedAttempt
    {
        return new StartedAttempt(\bin2hex(\random_bytes(16)), null);
    }

    /**
     * @param array<string, string> $callbackParams
     */
    public function callbackAuthority(array $callbackParams): ?string
    {
        $authority = $callbackParams['authority'] ?? '';

        return '' === $authority ? null : $authority;
    }

    /**
     * @param array<string, string|bool> $callbackParams
     */
    public function verify(string $authority, Money $amount, array $callbackParams): Verification
    {
        return new Verification(true === ($callbackParams['confirmed'] ?? null));
    }
}
