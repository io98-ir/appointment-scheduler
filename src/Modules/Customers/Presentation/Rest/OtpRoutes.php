<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Presentation\Rest;

use Vaqtyar\Kernel\Rest\ApiError;
use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Customers\Application\Captcha;
use Vaqtyar\Modules\Customers\Application\OtpService;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\PhoneNumber;

/**
 * The phone login of the widget (docs/api.md), all public:
 *
 *     GET  /otp/config    whether a booking needs a verified phone
 *     GET  /captcha       a sum to solve before a code is sent
 *     POST /otp/request   sends a one-time code (202)
 *     POST /otp/verify    the code for a phone session token (200)
 *
 * The two POSTs need the REST nonce and are limited per client; the service
 * limits per phone number too. A code is never in a reply.
 */
final class OtpRoutes
{
    private const READ_LIMIT = 120;
    private const REQUEST_LIMIT = 5;
    private const VERIFY_LIMIT = 10;

    /**
     * @param \Closure(): OtpService $otp Built when a request needs it.
     * @param \Closure(): bool $requiresVerification Whether bookings need a verified phone.
     */
    public function __construct(
        private readonly Router $router,
        private readonly \Closure $otp,
        private readonly Captcha $captcha,
        private readonly Clock $clock,
        private readonly \Closure $requiresVerification,
    ) {
    }

    public function register(): void
    {
        $this->router->add(
            '/otp/config',
            'GET',
            fn (): array => [
                'required' => ($this->requiresVerification)(),
                'code_length' => OtpService::CODE_LENGTH,
            ],
            Router::ANYONE,
            [],
            new RateLimit(self::READ_LIMIT, 60)
        );
        $this->router->add(
            '/captcha',
            'GET',
            fn (): array => $this->captcha->issue($this->clock->now()->getTimestamp()),
            Router::ANYONE,
            [],
            new RateLimit(self::READ_LIMIT, 60)
        );
        $this->router->add(
            '/otp/request',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => $this->send($request),
            Router::hasRestNonce(...),
            [
                'phone' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 32, 'required' => true],
                'captcha_token' => ['type' => 'string', 'maxLength' => 200, 'required' => true],
                'captcha_answer' => ['type' => 'string', 'maxLength' => 10, 'required' => true],
            ],
            new RateLimit(self::REQUEST_LIMIT, 60)
        );
        $this->router->add(
            '/otp/verify',
            'POST',
            fn (\WP_REST_Request $request): array => $this->verify($request),
            Router::hasRestNonce(...),
            [
                'phone' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 32, 'required' => true],
                'code' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 12, 'required' => true],
            ],
            new RateLimit(self::VERIFY_LIMIT, 60)
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private function send(\WP_REST_Request $request): \WP_REST_Response
    {
        $solved = $this->captcha->verify(
            self::string($request->get_param('captcha_token')),
            self::string($request->get_param('captcha_answer')),
            $this->clock->now()->getTimestamp()
        );
        if (!$solved) {
            throw new InvalidValue('invalid_captcha', 'The captcha answer is wrong or has expired.');
        }
        $wait = ($this->otp)()->request(PhoneNumber::fromInput(self::string($request->get_param('phone'))));
        if ($wait > 0) {
            throw new ApiError(
                429,
                'otp_rate_limited',
                \__('A code was sent recently. Please wait a moment before asking for another.', 'vaqtyar'),
                [],
                $wait
            );
        }

        return new \WP_REST_Response([
            'expires_in' => OtpService::TTL_SECONDS,
            'resend_after' => OtpService::RESEND_SECONDS,
        ], 202);
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     * @return array{token: string, expires_at: string}
     */
    private function verify(\WP_REST_Request $request): array
    {
        $session = ($this->otp)()->verify(
            PhoneNumber::fromInput(self::string($request->get_param('phone'))),
            self::string($request->get_param('code'))
        );
        if (null === $session) {
            throw new InvalidValue('invalid_code', 'The code is wrong, expired or already used.');
        }

        return ['token' => $session['token'], 'expires_at' => \gmdate(\DATE_ATOM, $session['expires_at'])];
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
