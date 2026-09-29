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
 * Zarinpal, API v4 (payment.zarinpal.com/pg/v4). Amounts are sent in rials
 * with currency IRR. The callback carries Authority and Status=OK|NOK; a NOK
 * is not asked about, an OK is verified with the API before it counts.
 */
final class ZarinpalGateway implements PaymentGateway
{
    private const API = 'https://payment.zarinpal.com/pg/v4/payment/';
    private const START_PAY = 'https://payment.zarinpal.com/pg/StartPay/';
    private const OK = 100;
    private const ALREADY_VERIFIED = 101;

    public function __construct(private readonly JsonHttp $http, private readonly string $merchantId)
    {
    }

    public function id(): string
    {
        return 'zarinpal';
    }

    public function start(int $appointmentId, Money $amount, string $callbackUrl): StartedAttempt
    {
        $answer = $this->http->post(self::API . 'request.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amount->amount,
            'currency' => 'IRR',
            'callback_url' => $callbackUrl,
            'description' => 'appointment ' . $appointmentId,
        ]);
        $data = self::data($answer);
        $authority = $data['authority'] ?? null;
        if (self::OK !== ($data['code'] ?? null) || !\is_string($authority) || '' === $authority) {
            throw new GatewayException('Zarinpal refused the request: code ' . self::codeOf($answer));
        }

        return new StartedAttempt($authority, self::START_PAY . $authority);
    }

    /**
     * @param array<string, string> $callbackParams
     */
    public function callbackAuthority(array $callbackParams): ?string
    {
        $authority = $callbackParams['Authority'] ?? '';

        return '' === $authority ? null : $authority;
    }

    /**
     * @param array<string, string> $callbackParams
     */
    public function verify(string $authority, Money $amount, array $callbackParams): Verification
    {
        // The reconciliation job passes no parameters and asks the API anyway.
        if ('NOK' === ($callbackParams['Status'] ?? '')) {
            return new Verification(false);
        }
        $answer = $this->http->post(self::API . 'verify.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amount->amount,
            'authority' => $authority,
        ]);
        $data = self::data($answer);
        $code = $data['code'] ?? null;
        if (self::OK !== $code && self::ALREADY_VERIFIED !== $code) {
            // A definite "no" carries a code in errors; a body with neither is not an answer.
            if ([] === $data && [] === self::errors($answer)) {
                throw new GatewayException('Zarinpal verify gave no answer.');
            }

            return new Verification(false);
        }
        $ref = $data['ref_id'] ?? null;
        $card = $data['card_pan'] ?? null;

        return new Verification(
            true,
            \is_int($ref) || \is_string($ref) ? (string) $ref : null,
            \is_string($card) ? $card : null
        );
    }

    /**
     * Zarinpal sends data as an object on success and as [] on failure.
     *
     * @param array<mixed> $answer
     * @return array<mixed>
     */
    private static function data(array $answer): array
    {
        return \is_array($answer['data'] ?? null) ? $answer['data'] : [];
    }

    /**
     * @param array<mixed> $answer
     * @return array<mixed>
     */
    private static function errors(array $answer): array
    {
        return \is_array($answer['errors'] ?? null) ? $answer['errors'] : [];
    }

    /**
     * @param array<mixed> $answer
     */
    private static function codeOf(array $answer): string
    {
        $code = self::errors($answer)['code'] ?? self::data($answer)['code'] ?? '?';

        return \is_int($code) || \is_string($code) ? (string) $code : '?';
    }
}
