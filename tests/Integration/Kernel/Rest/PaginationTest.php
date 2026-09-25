<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel\Rest;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Log\Logger;
use Vaqtyar\Kernel\RequestId;
use Vaqtyar\Kernel\Rest\Pagination;
use Vaqtyar\Kernel\Rest\RateLimiter;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Shared\SystemClock;

/**
 * page and per_page on a list route, with the X-WP-Total headers (architecture §9).
 */
final class PaginationTest extends \WP_UnitTestCase
{
    private const TOTAL = 45;

    public function set_up(): void
    {
        parent::set_up();
        $GLOBALS['wp_rest_server'] = null;
        $db = Db::fromGlobals();
        $requestId = new RequestId();
        $router = new Router(
            new RateLimiter($db, new SystemClock()),
            $requestId,
            new Logger($db, new SystemClock(), $requestId)
        );
        \add_action('rest_api_init', static function () use ($router): void {
            $router->add(
                '/items',
                'GET',
                static function (\WP_REST_Request $request): \WP_REST_Response {
                    $page = Pagination::fromRequest($request);

                    return $page->response(
                        \array_slice(\range(1, self::TOTAL), $page->offset(), $page->perPage),
                        self::TOTAL
                    );
                },
                static fn (): bool => true,
                Pagination::ARGS
            );
        });
    }

    public function tear_down(): void
    {
        $GLOBALS['wp_rest_server'] = null;
        parent::tear_down();
    }

    public function testReturnsTheRequestedPageWithTheTotals(): void
    {
        $response = $this->list(['page' => '2', 'per_page' => '20']);

        self::assertSame(200, $response->get_status());
        self::assertSame(\range(21, 40), $response->get_data());
        self::assertSame('45', $response->get_headers()['X-WP-Total'] ?? null);
        self::assertSame('3', $response->get_headers()['X-WP-TotalPages'] ?? null);
    }

    public function testDefaultsToTheFirstPageOfTwenty(): void
    {
        self::assertSame(\range(1, 20), $this->list([])->get_data());
    }

    public function testAPageAfterTheLastIsEmpty(): void
    {
        self::assertSame([], $this->list(['page' => '4'])->get_data());
    }

    public function testRefusesMoreThanAHundredPerPage(): void
    {
        self::assertSame(400, $this->list(['per_page' => '101'])->get_status());
    }

    public function testRefusesAPageBelowOne(): void
    {
        self::assertSame(400, $this->list(['page' => '0'])->get_status());
    }

    public function testRefusesAPageSoLargeTheOffsetWouldOverflow(): void
    {
        self::assertSame(400, $this->list(['page' => '184467440737095516'])->get_status());
    }

    /**
     * @param array<string, string> $query
     */
    private function list(array $query): \WP_REST_Response
    {
        $request = new \WP_REST_Request('GET', '/' . Identity::REST_NAMESPACE . '/items');
        $request->set_query_params($query);

        return \rest_do_request($request);
    }
}
