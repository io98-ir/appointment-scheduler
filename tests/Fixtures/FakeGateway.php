<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Fixtures;

use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Modules\Payments\Application\StartedAttempt;
use Vaqtyar\Modules\Payments\Application\Verification;
use Vaqtyar\Shared\Domain\Money;

/**
 * An online gateway for integration tests: it answers as the test says.
 * tests/Integration/bootstrap.php adds one to every site through the
 * gateways filter, since the registry is built once per request.
 */
final class FakeGateway implements PaymentGateway
{
    /** What verify() answers: the customer paid. */
    public static bool $paid = true;

    private static int $serial = 0;

    public function id(): string
    {
        return 'fake';
    }

    public function start(int $appointmentId, Money $amount, string $callbackUrl): StartedAttempt
    {
        $authority = 'F' . ++self::$serial;

        return new StartedAttempt($authority, 'https://pay.test/' . $authority);
    }

    /**
     * @param array<string, string> $callbackParams
     */
    public function callbackAuthority(array $callbackParams): ?string
    {
        return $callbackParams['authority'] ?? null;
    }

    /**
     * @param array<string, string> $callbackParams
     */
    public function verify(string $authority, Money $amount, array $callbackParams): Verification
    {
        return new Verification(self::$paid, self::$paid ? 'REF-' . $authority : null);
    }
}
