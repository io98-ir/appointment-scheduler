<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Booking;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;
use Vaqtyar\Tests\Integration\Modules\Catalog\CatalogTables;

/**
 * The admin time-rules API through the real REST server (T3.5): flat CRUD on
 * the "time" rows of price_rules, in the config shape WpdbPricingReader reads.
 */
final class TimeRuleRestTest extends TestCase
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
        $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('price_rules'));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function routes(): iterable
    {
        $body = ['from' => '18:00', 'to' => '22:00', 'percent' => 20];
        yield 'list' => ['GET', '/time-rules', []];
        yield 'create' => ['POST', '/time-rules', $body];
        yield 'update' => ['PUT', '/time-rules/1', $body];
        yield 'delete' => ['DELETE', '/time-rules/1', []];
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

    public function testATimeRuleGoesThroughItsWholeLife(): void
    {
        $this->logInAs('administrator');
        $service = $this->service();

        $created = $this->request('POST', '/time-rules', [
            'service_id' => $service,
            'priority' => 5,
            'weekdays' => [6],
            'from' => '18:00',
            'to' => '24:00',
            'valid_from' => '2027-03-20',
            'valid_to' => '2027-04-05',
            'percent' => 20,
        ]);
        $id = $created['body']['id'] ?? null;
        self::assertIsInt($id, (string) \wp_json_encode($created['body']));
        self::assertSame(
            [201, $service, 5, true, [6], '18:00', '24:00', '2027-03-20', '2027-04-05', 20],
            [
                $created['status'],
                ...self::pick($created['body'], [
                    'service_id', 'priority', 'active', 'weekdays', 'from', 'to', 'valid_from', 'valid_to', 'percent',
                ]),
            ]
        );

        self::assertSame([$id], \array_column($this->request('GET', '/time-rules')['body'], 'id'));

        $updated = $this->request('PUT', "/time-rules/{$id}", [
            'from' => '09:00',
            'to' => '12:00',
            'percent' => -10,
            'active' => false,
        ]);
        self::assertSame(
            [200, null, [], '09:00', '12:00', null, null, -10, false],
            [
                $updated['status'],
                ...self::pick($updated['body'], [
                    'service_id', 'weekdays', 'from', 'to', 'valid_from', 'valid_to', 'percent', 'active',
                ]),
            ]
        );

        self::assertSame(204, $this->request('DELETE', "/time-rules/{$id}")['status']);
        self::assertSame([], $this->request('GET', '/time-rules')['body']);
        $gone = $this->request('DELETE', "/time-rules/{$id}");
        self::assertSame([404, 'time_rule_not_found'], [$gone['status'], $gone['body']['code'] ?? null]);
    }

    public function testTheRuleIsStoredInTheShapeThePricingReaderReads(): void
    {
        $this->logInAs('administrator');
        $this->request('POST', '/time-rules', [
            'weekdays' => [5, 6],
            'from' => '18:00',
            'to' => '22:30',
            'percent' => 15,
        ]);

        $row = $this->realDb()->getResults('SELECT type, status, config FROM %i', Tables::name('price_rules'))[0];

        self::assertSame(['time', 'active'], [$row['type'], $row['status']]);
        self::assertSame(
            ['weekdays' => [5, 6], 'from' => '18:00', 'to' => '22:30', 'valid_from' => null, 'valid_to' => null,
                'percent' => 15],
            \json_decode((string) $row['config'], true)
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int, string}>
     */
    public static function invalid(): iterable
    {
        $base = ['from' => '18:00', 'to' => '22:00', 'percent' => 20];
        yield 'ends before it starts' => [['from' => '22:00', 'to' => '18:00'] + $base, 422, 'invalid_time_range'];
        yield 'zero percent' => [['percent' => 0] + $base, 422, 'invalid_percent'];
        yield 'over 1000 percent' => [['percent' => 1001] + $base, 422, 'invalid_percent'];
        yield 'dates reversed' => [
            ['valid_from' => '2027-02-01', 'valid_to' => '2027-01-01'] + $base,
            422,
            'invalid_date_range',
        ];
        yield 'unknown service' => [['service_id' => 999_999] + $base, 404, 'service_not_found'];
    }

    /**
     * @dataProvider invalid
     * @param array<string, mixed> $body
     */
    public function testAnInvalidRuleIsRejected(array $body, int $status, string $code): void
    {
        $this->logInAs('administrator');

        $response = $this->request('POST', '/time-rules', $body);

        self::assertSame([$status, $code], [$response['status'], $response['body']['code'] ?? null]);
        self::assertSame([], $this->request('GET', '/time-rules')['body']);
    }

    public function testUpdatingAnUnknownRuleIs404(): void
    {
        $this->logInAs('administrator');

        $response = $this->request('PUT', '/time-rules/999999', ['from' => '18:00', 'to' => '22:00', 'percent' => 5]);

        self::assertSame([404, 'time_rule_not_found'], [$response['status'], $response['body']['code'] ?? null]);
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
            'user_login' => 'timerule_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'timerule_' . $role . \count($this->users) . '@example.com',
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
