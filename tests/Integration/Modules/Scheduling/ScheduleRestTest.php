<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Scheduling;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;
use Vaqtyar\Tests\Integration\Modules\Catalog\CatalogTables;

/**
 * The admin schedule API through the real REST server, as the booted plugin
 * registered it.
 */
final class ScheduleRestTest extends TestCase
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
        foreach (['schedule_rules', 'schedule_exceptions'] as $table) {
            $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name($table));
        }
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    public function testTheModuleGaveAdministratorsTheScheduleCapability(): void
    {
        self::assertTrue(\get_role('administrator')?->has_cap(Caps::name('manage_schedules')));
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function routes(): iterable
    {
        $exception = ['owner_type' => 'staff', 'owner_id' => 1, 'date' => '2026-10-05', 'kind' => 'off'];
        yield 'read weekly' => ['GET', '/schedules/staff/1', []];
        yield 'save weekly' => ['PUT', '/schedules/staff/1', ['rules' => []]];
        yield 'list exceptions' => [
            'GET',
            '/schedule-exceptions',
            ['owner_type' => 'staff', 'owner_id' => 1, 'from' => '2026-10-01', 'to' => '2026-10-31'],
        ];
        yield 'create exception' => ['POST', '/schedule-exceptions', $exception];
        yield 'update exception' => ['PUT', '/schedule-exceptions/1', $exception];
        yield 'delete exception' => ['DELETE', '/schedule-exceptions/1', []];
    }

    /**
     * @dataProvider routes
     * @param array<string, mixed> $params
     */
    public function testAUserWithoutTheCapabilityIsForbiddenOnEveryRoute(
        string $method,
        string $path,
        array $params,
    ): void {
        $this->logInAs('editor');

        $response = $this->request($method, $path, $params);

        self::assertSame([403, 'rest_forbidden'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testAWeeklyScheduleIsSavedWholeAndReadBack(): void
    {
        $this->logInAs('administrator');
        $staff = $this->staffMember();
        $rules = [
            ['weekday' => 0, 'start' => '09:00', 'end' => '17:00', 'kind' => 'work'],
            ['weekday' => 0, 'start' => '12:00', 'end' => '13:00', 'kind' => 'break'],
            ['weekday' => 3, 'start' => '10:00', 'end' => '14:00', 'kind' => 'work'],
        ];

        $saved = $this->request('PUT', "/schedules/staff/{$staff}", ['rules' => $rules]);

        self::assertSame([200, $rules], [$saved['status'], $saved['body']['rules'] ?? null]);
        self::assertSame($rules, $this->request('GET', "/schedules/staff/{$staff}")['body']['rules'] ?? null);

        $cleared = $this->request('PUT', "/schedules/staff/{$staff}", ['rules' => []]);
        self::assertSame([], $cleared['body']['rules'] ?? null);
    }

    public function testABrokenScheduleIsRefusedWithItsCode(): void
    {
        $this->logInAs('administrator');
        $staff = $this->staffMember();

        $overlap = $this->request('PUT', "/schedules/staff/{$staff}", ['rules' => [
            ['weekday' => 1, 'start' => '09:00', 'end' => '13:00'],
            ['weekday' => 1, 'start' => '12:00', 'end' => '18:00'],
        ]]);
        $backwards = $this->request('PUT', "/schedules/staff/{$staff}", ['rules' => [
            ['weekday' => 1, 'start' => '13:00', 'end' => '09:00'],
        ]]);
        $unknown = $this->request('GET', '/schedules/resource/999');

        self::assertSame([422, 'overlapping_rules'], [$overlap['status'], $overlap['body']['code'] ?? null]);
        self::assertSame([422, 'invalid_time_range'], [$backwards['status'], $backwards['body']['code'] ?? null]);
        self::assertSame([404, 'resource_not_found'], [$unknown['status'], $unknown['body']['code'] ?? null]);
    }

    public function testTimeOffGoesThroughItsWholeLife(): void
    {
        $this->logInAs('administrator');
        $staff = $this->staffMember();

        $created = $this->request('POST', '/schedule-exceptions', [
            'owner_type' => 'staff',
            'owner_id' => $staff,
            'date' => '2026-10-05',
            'kind' => 'off',
            'note' => ' Leave ',
        ]);
        $id = $created['body']['id'] ?? null;
        self::assertIsInt($id);
        self::assertSame(201, $created['status']);
        self::assertSame(
            ['staff', $staff, '2026-10-05', null, null, 'off', 'Leave'],
            self::pick($created['body'], ['owner_type', 'owner_id', 'date', 'start', 'end', 'kind', 'note'])
        );

        $updated = $this->request('PUT', "/schedule-exceptions/{$id}", [
            'owner_type' => 'staff',
            'owner_id' => $staff,
            'date' => '2026-10-06',
            'start' => '14:00',
            'end' => '18:00',
            'kind' => 'blocked',
        ]);
        self::assertSame(
            [200, '2026-10-06', '14:00', '18:00', 'blocked'],
            [$updated['status'], ...self::pick($updated['body'], ['date', 'start', 'end', 'kind'])]
        );

        $range = ['owner_type' => 'staff', 'owner_id' => $staff, 'from' => '2026-10-01', 'to' => '2026-10-31'];
        $listed = $this->request('GET', '/schedule-exceptions', $range);
        self::assertSame([$id], \array_column($listed['body'], 'id'));

        self::assertSame(204, $this->request('DELETE', "/schedule-exceptions/{$id}")['status']);
        self::assertSame([], $this->request('GET', '/schedule-exceptions', $range)['body']);
        $gone = $this->request('DELETE', "/schedule-exceptions/{$id}");
        self::assertSame([404, 'exception_not_found'], [$gone['status'], $gone['body']['code'] ?? null]);
    }

    public function testExtraHoursNeedAStartAndAnEnd(): void
    {
        $this->logInAs('administrator');

        $response = $this->request('POST', '/schedule-exceptions', [
            'owner_type' => 'staff',
            'owner_id' => $this->staffMember(),
            'date' => '2026-10-05',
            'kind' => 'extra',
        ]);

        self::assertSame([422, 'extra_needs_hours'], [$response['status'], $response['body']['code'] ?? null]);
    }

    private function staffMember(): int
    {
        $created = $this->request('POST', '/staff', ['name' => 'Dr. Karimi', 'color' => '#112233']);
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
            'user_login' => 'schedule_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'schedule_' . $role . \count($this->users) . '@example.com',
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
