<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Scheduling;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * The admin holiday-calendar API through the real REST server, as the
 * booted plugin registered it. It shares Booking's own capability, granted
 * there (BookingModule::capabilities()).
 */
final class HolidayRestTest extends TestCase
{
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
        $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('holidays'));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function routes(): iterable
    {
        yield 'list' => ['GET', '/holidays', ['calendar' => 'ir', 'from' => '2026-10-01', 'to' => '2026-10-31']];
        yield 'save' => ['POST', '/holidays', ['calendar' => 'ir', 'date' => '2026-10-05', 'title' => 'Test']];
        yield 'delete' => ['DELETE', '/holidays/ir/2026-10-05', []];
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

    public function testAHolidayGoesThroughItsWholeLife(): void
    {
        $this->logInAs('administrator');

        $created = $this->request('POST', '/holidays', [
            'calendar' => 'test',
            'date' => '2026-10-05',
            'title' => ' Test day ',
        ]);
        self::assertSame(
            [201, 'test', '2026-10-05', 'Test day', 'manual'],
            [$created['status'], ...self::pick($created['body'], ['calendar', 'date', 'title', 'source'])]
        );

        $renamed = $this->request('POST', '/holidays', [
            'calendar' => 'test',
            'date' => '2026-10-05',
            'title' => 'Renamed',
        ]);
        self::assertSame([201, 'Renamed'], [$renamed['status'], $renamed['body']['title'] ?? null]);

        $range = ['calendar' => 'test', 'from' => '2026-10-01', 'to' => '2026-10-31'];
        $listed = $this->request('GET', '/holidays', $range);
        self::assertSame(['2026-10-05'], \array_column($listed['body'], 'date'));

        self::assertSame(204, $this->request('DELETE', '/holidays/test/2026-10-05')['status']);
        self::assertSame([], $this->request('GET', '/holidays', $range)['body']);
        $gone = $this->request('DELETE', '/holidays/test/2026-10-05');
        self::assertSame([404, 'holiday_not_found'], [$gone['status'], $gone['body']['code'] ?? null]);
    }

    public function testARangeLongerThanAYearIsRefused(): void
    {
        $this->logInAs('administrator');

        $response = $this->request('GET', '/holidays', [
            'calendar' => 'test',
            'from' => '2025-01-01',
            'to' => '2026-12-31',
        ]);

        self::assertSame([422, 'invalid_range'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testAnUnknownCalendarIsJustEmpty(): void
    {
        $this->logInAs('administrator');

        $response = $this->request('GET', '/holidays', [
            'calendar' => 'unknown',
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]);

        self::assertSame([200, []], [$response['status'], $response['body']]);
    }

    public function testTheModuleGaveAdministratorsTheCapability(): void
    {
        self::assertTrue(\get_role('administrator')?->has_cap(Caps::name('manage_bookings')));
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
            'user_login' => 'holiday_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'holiday_' . $role . \count($this->users) . '@example.com',
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
