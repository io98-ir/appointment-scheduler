<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Rest;

use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\KernelException;
use Vaqtyar\Kernel\Log\Logger;
use Vaqtyar\Kernel\RequestId;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\NotFound;

/**
 * Registers REST routes under the plugin's namespace and is the error
 * boundary around them (architecture §8, §9): every exception becomes the
 * error envelope, never a fatal error or a PHP message in the response.
 *
 *     {"code": "…", "message": "…", "data": {"status": 422, "details": {…}, "request_id": "…"}}
 *
 * Controllers are plain classes that call add() on rest_api_init; there is no
 * base class to extend.
 *
 * Errors WordPress raises before our code runs (unknown route, a parameter
 * that fails the args schema: 400 rest_invalid_param) keep its own shape,
 * which has code, message and data.status too.
 */
final class Router
{
    /**
     * The permission of a public read, e.g. availability. Accepted only on a
     * GET route with a rate limit (implementation-notes §6).
     */
    public const ANYONE = '__return_true';

    /** Seconds a client waits after a 503 busy, when locks were still contended after the retries. */
    private const BUSY_RETRY_SECONDS = 2;

    /**
     * The permission of a public write, e.g. a hold from the booking
     * widget: a guest has no session, so the REST nonce (wp_rest, sent as
     * X-WP-Nonce) is the proof the request came from the site's own page
     * (architecture §12). Use it with a rate limit.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    public static function hasRestNonce(\WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');

        return \is_string($nonce) && false !== \wp_verify_nonce($nonce, 'wp_rest');
    }

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly RequestId $requestId,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Call on rest_api_init.
     *
     * @param non-falsy-string $path Relative to the namespace, e.g. "/services/(?P<id>\d+)".
     * @param string $methods One of the WP_REST_Server constants, e.g. WP_REST_Server::READABLE.
     * @param callable(\WP_REST_Request<array<string, mixed>>): mixed $callback Returns the
     *     response data or a WP_REST_Response. Fails by throwing: ApiError,
     *     InvalidValue (422), NotFound (404), Conflict (409), Forbidden (401 or 403), or
     *     anything else for a 500. A returned WP_Error is wrapped in the
     *     envelope too.
     * @param callable(\WP_REST_Request<array<string, mixed>>): bool $permission Runs before
     *     the callback; only true lets the request through. Authorization is
     *     checked again in the Application layer (architecture §12).
     * @param array<string, array<string, mixed>> $args The args schema; WordPress validates it.
     * @param RateLimit|null $rateLimit Counted per client address, after the permission check.
     */
    public function add(
        string $path,
        string $methods,
        callable $callback,
        callable $permission,
        array $args = [],
        ?RateLimit $rateLimit = null,
    ): void {
        if (self::ANYONE === $permission && ('GET' !== $methods || null === $rateLimit)) {
            throw KernelException::unprotectedRoute($methods, $path);
        }

        \register_rest_route(Identity::REST_NAMESPACE, $path, [
            'methods' => $methods,
            'callback' => fn (\WP_REST_Request $request): \WP_REST_Response => $this->respond(
                $request,
                $callback,
                null === $rateLimit ? null : [$rateLimit, "{$methods} {$path}"]
            ),
            'permission_callback' => fn (\WP_REST_Request $request): bool|\WP_Error => $this->authorize(
                $request,
                $permission
            ),
            'args' => $args,
        ]);
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     * @param callable(\WP_REST_Request<array<string, mixed>>): bool $permission
     */
    private function authorize(\WP_REST_Request $request, callable $permission): bool|\WP_Error
    {
        try {
            if (true === $permission($request)) {
                return true;
            }
        } catch (\Throwable $e) {
            return $this->toError($e);
        }

        // 401 for a guest, 403 for a user without the capability, as WordPress does.
        return $this->error(
            \rest_authorization_required_code(),
            'rest_forbidden',
            \__('Sorry, you are not allowed to do that.', 'vaqtyar')
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     * @param callable(\WP_REST_Request<array<string, mixed>>): mixed $callback
     * @param array{RateLimit, string}|null $rateLimit The rule and the route it counts for.
     */
    private function respond(
        \WP_REST_Request $request,
        callable $callback,
        ?array $rateLimit,
    ): \WP_REST_Response {
        try {
            if (null !== $rateLimit) {
                [$rule, $route] = $rateLimit;
                $wait = $this->limiter->attempt(
                    "rest:{$route}:ip:" . ClientIp::fromRequest()->rateLimitKey(),
                    $rule
                );
                if ($wait > 0) {
                    throw ApiError::tooManyRequests($wait);
                }
            }

            $result = $callback($request);
            if ($result instanceof \WP_Error) {
                // Into the envelope too, rather than passed through without a request id.
                $data = $result->get_error_data();
                $status = \is_array($data) && \is_int($data['status'] ?? null) ? $data['status'] : 500;

                return \rest_convert_error_to_response(
                    $this->error($status, (string) $result->get_error_code(), $result->get_error_message())
                );
            }

            return \rest_ensure_response($result);
        } catch (\Throwable $e) {
            $response = \rest_convert_error_to_response($this->toError($e));
            if ($e instanceof ApiError && null !== $e->retryAfter) {
                $response->header('Retry-After', (string) $e->retryAfter);
            }
            if ($e instanceof DbException && $e->isRetryable()) {
                $response->header('Retry-After', (string) self::BUSY_RETRY_SECONDS);
            }

            return $response;
        }
    }

    private function toError(\Throwable $e): \WP_Error
    {
        if ($e instanceof ApiError) {
            return $this->error($e->status, $e->errorCode, $e->getMessage(), $e->details);
        }
        if ($e instanceof InvalidValue) {
            // The code tells the client which rule failed; the Domain has no
            // translated text (principles §3).
            return $this->error(422, $e->errorCode, \__('A value in the request is not valid.', 'vaqtyar'));
        }
        if ($e instanceof NotFound) {
            return $this->error(404, $e->errorCode, \__('The requested item does not exist.', 'vaqtyar'));
        }
        if ($e instanceof Conflict) {
            return $this->error(
                409,
                $e->errorCode,
                \__('The request conflicts with the current state. Please try again.', 'vaqtyar')
            );
        }
        if ($e instanceof DbException && $e->isRetryable()) {
            // Still locked out after the Transaction's retries: busy, not broken.
            return $this->error(
                503,
                'busy',
                \__('The server is busy. Please try again in a moment.', 'vaqtyar'),
                ['retry_after' => self::BUSY_RETRY_SECONDS]
            );
        }
        if ($e instanceof Forbidden) {
            // The same answer as a refused permission callback.
            return $this->error(
                \rest_authorization_required_code(),
                'rest_forbidden',
                \__('Sorry, you are not allowed to do that.', 'vaqtyar')
            );
        }

        // A bug or a server condition. The client gets a generic message; the
        // log gets the exception, personal data masked, under the request id
        // the client sees.
        $this->logger->error('rest', 'A REST request failed.', ['exception' => $e]);

        return $this->error(500, 'internal_error', \__('Something went wrong on the server.', 'vaqtyar'));
    }

    /**
     * @param array<string, mixed> $details
     */
    private function error(int $status, string $code, string $message, array $details = []): \WP_Error
    {
        return new \WP_Error($code, $message, [
            'status' => $status,
            // An object in JSON even when empty, so clients can rely on its type.
            'details' => [] === $details ? new \stdClass() : $details,
            'request_id' => $this->requestId->value(),
        ]);
    }
}
