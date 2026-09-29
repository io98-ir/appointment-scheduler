<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Booking;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;
use Vaqtyar\Tests\Integration\Modules\Catalog\CatalogTables;

/**
 * The admin coupons API through the real REST server (T3.5): flat CRUD on
 * the coupons table, the unique code, and that `used` is never writable.
 */
final class CouponRestTest extends TestCase
{
    use CatalogTables;
    use RealDatabase;

    /** @var list<int> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->realDb();
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core's global, reset as core's tests do.
        $GLOBALS['wp_rest_server'] = null;
    }

    protected function tearDown(): void
    {
        \wp_set_current_user(0);
        require_once \ABSPATH . 'wp-admin/includes/user.php';
        foreach ($this->users as $id) {
            \wp_delete_user($id);
        }
        $this->emptyCatalogTables();
        $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('coupons'));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function routes(): iterable
    {
        $body = ['code' => 'X', 'type' => 'percent', 'value' => 10];
        yield 'list' => ['GET', '/coupons', []];
        yield 'create' => ['POST', '/coupons', $body];
        yield 'update' => ['PUT', '/coupons/1', $body];
        yield 'delete' => ['DELETE', '/coupons/1', []];
    }

    /**
     * @dataProvider routes
     * @param array<string, mixed> $params
     */
    public function testAUserWithoutTheCapabilityIsForbidden(string $method, string $path, array $params): void
    {
        $this->logInAs('editor');

        $response = $this->request($method, $path, $params);

        self::assertSame([403, 'rest_forbidden'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testACouponGoesThroughItsWholeLife(): void
    {
        $this->logInAs('administrator');
        $service = $this->service();

        $created = $this->request('POST', '/coupons', [
            'code' => 'NOWRUZ',
            'type' => 'percent',
            'value' => 15,
            'valid_from' => '2027-03-20T00:00:00Z',
            'valid_to' => '2027-04-05T00:00:00Z',
            'max_uses' => 100,
            'service_ids' => [$service],
        ]);
        $id = $created['body']['id'] ?? null;
        self::assertIsInt($id, (string) \wp_json_encode($created['body']));
        self::assertSame(
            [201, 'NOWRUZ', 'percent', 15, true, '2027-03-20T00:00:00Z', '2027-04-05T00:00:00Z', 100, 0, [$service]],
            [
                $created['status'],
                ...self::pick($created['body'], [
                    'code', 'type', 'value', 'active', 'valid_from', 'valid_to', 'max_uses', 'used', 'service_ids',
                ]),
            ]
        );

        self::assertSame([$id], \array_column($this->request('GET', '/coupons')['body'], 'id'));

        $updated = $this->request('PUT', "/coupons/{$id}", [
            'code' => 'NOWRUZ',
            'type' => 'fixed',
            'value' => 50_000,
            'active' => false,
        ]);
        self::assertSame(
            [200, 'fixed', 50_000, false, null, null, null, null],
            [
                $updated['status'],
                ...self::pick($updated['body'], [
                    'type', 'value', 'active', 'valid_from', 'valid_to', 'max_uses', 'service_ids',
                ]),
            ]
        );

        self::assertSame(204, $this->request('DELETE', "/coupons/{$id}")['status']);
        self::assertSame([], $this->request('GET', '/coupons')['body']);
        $gone = $this->request('DELETE', "/coupons/{$id}");
        self::assertSame([404, 'coupon_not_found'], [$gone['status'], $gone['body']['code'] ?? null]);
    }

    public function testUsedSurvivesAnEditAndCannotBeSet(): void
    {
        $this->logInAs('administrator');
        $id = $this->create('ONCE');
        $this->realDb()->execute('UPDATE %i SET used = 3 WHERE id = %d', Tables::name('coupons'), $id);

        $updated = $this->request('PUT', "/coupons/{$id}", [
            'code' => 'ONCE', 'type' => 'percent', 'value' => 20, 'used' => 0,
        ]);

        self::assertSame(
            [200, 20, 3],
            [$updated['status'], $updated['body']['value'] ?? null, $updated['body']['used'] ?? null]
        );
    }

    public function testACodeIsUniqueWithoutRegardToCase(): void
    {
        $this->logInAs('administrator');
        $first = $this->create('SUMMER');
        $other = $this->create('AUTUMN');

        $created = $this->request('POST', '/coupons', ['code' => 'summer', 'type' => 'percent', 'value' => 5]);
        self::assertSame([409, 'coupon_code_taken'], [$created['status'], $created['body']['code'] ?? null]);

        $renamed = $this->request('PUT', "/coupons/{$other}", ['code' => 'Summer', 'type' => 'percent', 'value' => 5]);
        self::assertSame([409, 'coupon_code_taken'], [$renamed['status'], $renamed['body']['code'] ?? null]);

        $same = $this->request('PUT', "/coupons/{$first}", ['code' => 'summer', 'type' => 'percent', 'value' => 7]);
        self::assertSame([200, 7], [$same['status'], $same['body']['value'] ?? null]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int, string}>
     */
    public static function invalid(): iterable
    {
        $base = ['code' => 'BAD', 'type' => 'percent', 'value' => 10];
        yield 'over 100 percent' => [['value' => 101] + $base, 422, 'invalid_coupon_value'];
        yield 'blank code' => [['code' => '  '] + $base, 422, 'invalid_coupon'];
        yield 'ends before it starts' => [
            ['valid_from' => '2027-02-01T00:00:00Z', 'valid_to' => '2027-01-01T00:00:00Z'] + $base,
            422,
            'invalid_coupon',
        ];
        yield 'unknown service' => [['service_ids' => [999_999]] + $base, 404, 'service_not_found'];
    }

    /**
     * @dataProvider invalid
     * @param array<string, mixed> $body
     */
    public function testAnInvalidCouponIsRejected(array $body, int $status, string $code): void
    {
        $this->logInAs('administrator');

        $response = $this->request('POST', '/coupons', $body);

        self::assertSame([$status, $code], [$response['status'], $response['body']['code'] ?? null]);
        self::assertSame([], $this->request('GET', '/coupons')['body']);
    }

    public function testUpdatingAnUnknownCouponIs404(): void
    {
        $this->logInAs('administrator');

        $response = $this->request('PUT', '/coupons/999999', ['code' => 'X', 'type' => 'percent', 'value' => 10]);

        self::assertSame([404, 'coupon_not_found'], [$response['status'], $response['body']['code'] ?? null]);
    }

    private function create(string $code): int
    {
        $created = $this->request('POST', '/coupons', ['code' => $code, 'type' => 'percent', 'value' => 10]);
        $id = $created['body']['id'] ?? null;
        self::assertIsInt($id, (string) \wp_json_encode($created['body']));

        return $id;
    }

    private function service(): int
    {
        $created = $this->request('POST', '/services', [
            'name' => 'Consultation',
            'variants' => [[
                'label' => '30 min',
                'is_default' => true,
                'duration_min' => 30,
                'price' => ['amount' => 1_000_000, 'currency' => 'IRR'],
            ]],
        ]);
        $id = $created['body']['id'] ?? null;
        self::assertIsInt($id, (string) \wp_json_encode($created['body']));

        return $id;
    }

    /**
     * @param array<mixed> $body
     * @param list<string> $keys
     * @return list<mixed>
     */
    private static function pick(array $body, array $keys): array
    {
        return \array_map(static fn (string $key): mixed => $body[$key] ?? null, $keys);
    }

    private function logInAs(string $role): void
    {
        $id = \wp_insert_user([
            'user_login' => 'coupon_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'coupon_' . $role . \count($this->users) . '@example.com',
            'role' => $role,
        ]);
        self::assertIsInt($id);
        $this->users[] = $id;
        \wp_set_current_user($id);
    }

    /**
     * @param array<string, mixed> $params A JSON body, or the query for GET.
     * @return array{status: int, body: array<mixed>}
     */
    private function request(string $method, string $path, array $params = []): array
    {
        $request = new \WP_REST_Request($method, '/' . Identity::REST_NAMESPACE . $path);
        if ('GET' === $method) {
            $request->set_query_params($params);
        } else {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body((string) \wp_json_encode($params));
        }
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
    }
}
