<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel\Rest;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\KernelException;
use Vaqtyar\Kernel\Log\Logger;
use Vaqtyar\Kernel\RequestId;
use Vaqtyar\Kernel\Rest\ApiError;
use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\RateLimiter;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Tests\Fixtures\FixedClock;

/**
 * Routes registered through the Router, called through the real REST server.
 *
 * @phpstan-type Envelope array{
 *     code: string,
 *     message: string,
 *     data: array{status: int, details: array<string, mixed>|\stdClass, request_id: string}
 * }
 */
final class RouterTest extends \WP_UnitTestCase
{
    private Router $router;

    private RequestId $requestId;

    public function set_up(): void
    {
        parent::set_up();
        $this->requestId = new RequestId();
        $clock = new FixedClock('2026-09-25 10:00:30');
        $this->router = new Router(
            new RateLimiter(Db::fromGlobals(), $clock),
            $this->requestId,
            new Logger(Db::fromGlobals(), $clock, $this->requestId)
        );
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        // A fresh server, so rest_api_init runs again with this test's routes.
        $GLOBALS['wp_rest_server'] = null;
    }

    public function tear_down(): void
    {
        $GLOBALS['wp_rest_server'] = null;
        unset($_SERVER['REMOTE_ADDR']);
        parent::tear_down();
    }

    public function testASampleEndpointAnswersUnderThePluginNamespace(): void
    {
        $this->routes(function (): void {
            $this->router->add(
                '/sample/(?P<id>\d+)',
                'GET',
                static fn (\WP_REST_Request $request): array => ['id' => $request->get_param('id')],
                static fn (): bool => true,
                ['id' => ['type' => 'integer']]
            );
        });

        $response = $this->get('/sample/7');

        self::assertSame(200, $response->get_status());
        self::assertSame(['id' => 7], $response->get_data());
        $route = '/' . Identity::REST_NAMESPACE . '/sample/(?P<id>\d+)';
        self::assertArrayHasKey($route, \rest_get_server()->get_routes());
    }

    public function testAGuestIsAskedToAuthenticate(): void
    {
        $this->adminOnlyRoute();

        $response = $this->get('/admin-only');

        self::assertSame(401, $response->get_status());
        $this->assertEnvelope($response, 'rest_forbidden', 401);
    }

    public function testAUserWithoutTheCapabilityIsForbidden(): void
    {
        $this->logInAs('subscriber');
        $this->adminOnlyRoute();

        $response = $this->get('/admin-only');

        self::assertSame(403, $response->get_status());
        $this->assertEnvelope($response, 'rest_forbidden', 403);
    }

    public function testAUserWithTheCapabilityGetsThrough(): void
    {
        $this->logInAs('administrator');
        $this->adminOnlyRoute();

        self::assertSame(200, $this->get('/admin-only')->get_status());
    }

    public function testAnInvalidValueBecomes422WithItsErrorCode(): void
    {
        $this->throwingRoute(new InvalidValue('invalid_phone', 'The phone number is not valid.'));

        $response = $this->get('/throws');

        self::assertSame(422, $response->get_status());
        $this->assertEnvelope($response, 'invalid_phone', 422);
    }

    public function testAnApiErrorKeepsItsStatusCodeAndDetails(): void
    {
        $this->throwingRoute(new ApiError(404, 'service_not_found', 'No such service.', ['id' => 5]));

        $response = $this->get('/throws');

        self::assertSame(404, $response->get_status());
        $data = $this->assertEnvelope($response, 'service_not_found', 404);
        self::assertSame('No such service.', $data['message']);
        self::assertSame(['id' => 5], $data['data']['details']);
    }

    public function testNotFoundBecomes404WithItsErrorCode(): void
    {
        $this->throwingRoute(new NotFound('location_not_found', 'No such location.'));

        $response = $this->get('/throws');

        self::assertSame(404, $response->get_status());
        $this->assertEnvelope($response, 'location_not_found', 404);
    }

    public function testForbiddenFromTheApplicationLayerAnswersLikeARefusedPermission(): void
    {
        $this->logInAs('subscriber');
        $this->throwingRoute(new Forbidden('manage_catalog'));

        $response = $this->get('/throws');

        self::assertSame(403, $response->get_status());
        $this->assertEnvelope($response, 'rest_forbidden', 403);
    }

    public function testAnErrorInThePermissionCheckIsMappedToo(): void
    {
        $this->routes(function (): void {
            $this->router->add(
                '/permission-throws',
                'GET',
                static fn (): array => [],
                static function (): bool {
                    throw new ApiError(404, 'appointment_not_found', 'No such appointment.');
                }
            );
        });

        $this->assertEnvelope($this->get('/permission-throws'), 'appointment_not_found', 404);
    }

    public function testAnUnexpectedErrorHidesItsTextAndLogsIt(): void
    {
        $this->throwingRoute(new \RuntimeException('Duplicate entry 09121234567'));

        $response = $this->get('/throws');

        self::assertSame(500, $response->get_status());
        $data = $this->assertEnvelope($response, 'internal_error', 500);
        self::assertStringNotContainsString('0912', (string) \wp_json_encode($data));
        // The log line carries the request id, so a support request can find it.
        $logged = Db::fromGlobals()->getVar(
            'SELECT context FROM %i WHERE request_id = %s AND channel = %s',
            Tables::name('logs'),
            $this->requestId->value(),
            'rest'
        );
        self::assertIsString($logged);
        self::assertStringContainsString('RuntimeException', $logged);
        // Masked, so no personal data reaches the log (principles §7).
        self::assertStringContainsString('Duplicate entry ***4567', $logged);
    }

    public function testAReturnedWpErrorGetsTheEnvelopeToo(): void
    {
        $this->routes(function (): void {
            $this->router->add(
                '/returns-error',
                'GET',
                static fn (): \WP_Error => new \WP_Error('slot_taken', 'The slot is taken.', ['status' => 409]),
                static fn (): bool => true
            );
        });

        $response = $this->get('/returns-error');

        self::assertSame(409, $response->get_status());
        $this->assertEnvelope($response, 'slot_taken', 409);
    }

    public function testTheRateLimitAnswers429WithRetryAfter(): void
    {
        $this->limitedRoute(new RateLimit(2, 60));

        $statuses = [$this->get('/limited')->get_status(), $this->get('/limited')->get_status()];
        $response = $this->get('/limited');

        self::assertSame([200, 200], $statuses);

        self::assertSame(429, $response->get_status());
        $this->assertEnvelope($response, 'rate_limited', 429);
        // The window is 10:00:00 to 10:01:00 and the clock says 10:00:30.
        self::assertSame('30', $response->get_headers()['Retry-After'] ?? null);
    }

    public function testTheRateLimitCountsEachClientApart(): void
    {
        $this->limitedRoute(new RateLimit(1, 60));

        $first = $this->get('/limited')->get_status();
        $_SERVER['REMOTE_ADDR'] = '198.51.100.4';
        $second = $this->get('/limited')->get_status();

        self::assertSame([200, 200], [$first, $second]);
    }

    public function testTheRateLimitCountsEachRouteApart(): void
    {
        $this->limitedRoute(new RateLimit(1, 60));
        $this->routes(function (): void {
            $this->router->add(
                '/limited-too',
                'GET',
                static fn (): array => [],
                Router::ANYONE,
                [],
                new RateLimit(1, 60)
            );
        });

        self::assertSame(200, $this->get('/limited')->get_status());
        self::assertSame(200, $this->get('/limited-too')->get_status());
    }

    public function testAPublicRouteCannotChangeData(): void
    {
        $this->expectException(KernelException::class);

        $this->routes(function (): void {
            $this->router->add('/open', 'POST', static fn (): array => [], Router::ANYONE, [], new RateLimit(5, 60));
        });
        \rest_get_server();
    }

    public function testAPublicRouteNeedsARateLimit(): void
    {
        $this->expectException(KernelException::class);

        $this->routes(function (): void {
            $this->router->add('/open', 'GET', static fn (): array => [], Router::ANYONE);
        });
        \rest_get_server();
    }

    private function logInAs(string $role): void
    {
        $id = self::factory()->user->create(['role' => $role]);
        self::assertIsInt($id);
        \wp_set_current_user($id);
    }

    private function adminOnlyRoute(): void
    {
        $this->routes(function (): void {
            $this->router->add(
                '/admin-only',
                'GET',
                static fn (): array => ['ok' => true],
                static fn (): bool => \current_user_can('manage_options')
            );
        });
    }

    private function throwingRoute(\Throwable $error): void
    {
        $this->routes(function () use ($error): void {
            $this->router->add(
                '/throws',
                'GET',
                static function () use ($error): array {
                    throw $error;
                },
                static fn (): bool => true
            );
        });
    }

    private function limitedRoute(RateLimit $limit): void
    {
        $this->routes(function () use ($limit): void {
            $this->router->add('/limited', 'GET', static fn (): array => ['ok' => true], Router::ANYONE, [], $limit);
        });
    }

    /**
     * The routes register when the server is built, on the first request.
     */
    private function routes(\Closure $register): void
    {
        \add_action('rest_api_init', $register);
    }

    /**
     * @phpstan-impure
     */
    private function get(string $path): \WP_REST_Response
    {
        return \rest_do_request(new \WP_REST_Request('GET', '/' . Identity::REST_NAMESPACE . $path));
    }

    /**
     * The error envelope of architecture §8.
     *
     * @return Envelope
     */
    private function assertEnvelope(\WP_REST_Response $response, string $code, int $status): array
    {
        $data = $response->get_data();
        self::assertIsArray($data);
        self::assertSame($code, $data['code'] ?? null);
        self::assertIsString($data['message'] ?? null);
        self::assertNotSame('', $data['message']);
        self::assertIsArray($data['data'] ?? null);
        self::assertSame($status, $data['data']['status'] ?? null);
        // An object in JSON, also when empty.
        self::assertTrue(\is_array($data['data']['details'] ?? null) || $data['data']['details'] instanceof \stdClass);
        self::assertSame($this->requestId->value(), $data['data']['request_id'] ?? null);

        /** @var Envelope */
        return $data;
    }
}
