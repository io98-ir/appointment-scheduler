<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Infrastructure;

use Vaqtyar\Modules\Payments\Application\GatewayException;
use Vaqtyar\Modules\Payments\Application\JsonHttp;
use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Modules\Payments\Application\StartedAttempt;
use Vaqtyar\Modules\Payments\Application\Verification;
use Vaqtyar\Shared\Domain\Money;

/**
 * Zibal (gateway.zibal.ir/v1). Amounts are rials. The callback carries
 * trackId, success and status; success=0 is not asked about, anything else
 * is verified, and the verified amount must equal ours. The merchant "zibal"
 * is Zibal's own test merchant.
 */
final class ZibalGateway implements PaymentGateway
{
    private const API = 'https://gateway.zibal.ir/v1/';
    private const START = 'https://gateway.zibal.ir/start/';
    private const OK = 100;
    private const ALREADY_VERIFIED = 201;

    public function __construct(private readonly JsonHttp $http, private readonly string $merchant)
    {
    }

    public function id(): string
    {
        return 'zibal';
    }

    public function start(int $appointmentId, Money $amount, string $callbackUrl): StartedAttempt
    {
        $answer = $this->http->post(self::API . 'request', [
            'merchant' => $this->merchant,
            'amount' => $amount->amount,
            'callbackUrl' => $callbackUrl,
            'description' => 'appointment ' . $appointmentId,
        ]);
        $track = $answer['trackId'] ?? null;
        $track = \is_int($track) || \is_string($track) ? (string) $track : '';
        if (self::OK !== ($answer['result'] ?? null) || '' === $track) {
            throw new GatewayException('Zibal refused the request: result ' . self::text($answer['result'] ?? '?'));
        }

        return new StartedAttempt($track, self::START . $track);
    }

    /**
     * @param array<string, string> $callbackParams
     */
    public function callbackAuthority(array $callbackParams): ?string
    {
        $track = $callbackParams['trackId'] ?? '';

        return '' === $track ? null : $track;
    }

    /**
     * @param array<string, string> $callbackParams
     */
    public function verify(string $authority, Money $amount, array $callbackParams): Verification
    {
        if ('0' === ($callbackParams['success'] ?? '')) {
            return new Verification(false);
        }
        $answer = $this->http->post(
            self::API . 'verify',
            ['merchant' => $this->merchant, 'trackId' => (int) $authority]
        );
        $result = $answer['result'] ?? null;
        if (!\is_int($result)) {
            throw new GatewayException('Zibal verify gave no result.');
        }
        $verified = self::OK === $result || self::ALREADY_VERIFIED === $result;
        if (!$verified || ($answer['amount'] ?? null) !== $amount->amount) {
            return new Verification(false);
        }
        $ref = $answer['refNumber'] ?? null;
        $card = $answer['cardNumber'] ?? null;

        return new Verification(
            true,
            \is_int($ref) || \is_string($ref) ? (string) $ref : null,
            \is_string($card) ? $card : null
        );
    }

    private static function text(mixed $value): string
    {
        return \is_int($value) || \is_string($value) ? (string) $value : '?';
    }
}
