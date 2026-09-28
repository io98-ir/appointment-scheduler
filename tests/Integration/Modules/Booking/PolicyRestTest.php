<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Booking;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;
use Vaqtyar\Tests\Integration\Modules\Catalog\CatalogTables;

/**
 * The admin policy API through the real REST server (implementation-notes
 * §4.12): upsert on UNIQUE(type, service_id) and the not-set/global/service
 * levels PolicyAdminService and WpdbPolicyRepository build on.
 */
final class PolicyRestTest extends TestCase
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
        $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('policies'));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    public function testTheModuleGaveAdministratorsTheBookingCapability(): void
    {
        self::assertTrue(\get_role('administrator')?->has_cap(Caps::name('manage_bookings')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routes(): iterable
    {
        yield 'read' => ['GET', '/policies/cancellation/0'];
        yield 'save' => ['PUT', '/policies/cancellation/0'];
        yield 'delete' => ['DELETE', '/policies/cancellation/0'];
    }

    /**
     * @dataProvider routes
     */
    public function testAUserWithoutTheCapabilityIsForbidden(string $method, string $path): void
    {
        $this->logInAs('editor');

        $response = $this->request($method, $path);

        self::assertSame([403, 'rest_forbidden'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testAnUnsetPolicyReadsAsNoConfig(): void
    {
        $this->logInAs('administrator');

        self::assertSame(
            ['config' => null],
            $this->request('GET', '/policies/cancellation/0')['body']
        );
    }

    public function testACancellationPolicyIsUpsertedReadAndDeleted(): void
    {
        $this->logInAs('administrator');
        $config = [
            'notice_hours' => 24,
            'refund' => [['hours' => 48, 'percent' => 100], ['hours' => 24, 'percent' => 50]],
        ];

        $saved = $this->request('PUT', '/policies/cancellation/0', $config);
        self::assertSame([200, $config], [$saved['status'], $saved['body']['config'] ?? null]);
        self::assertSame($config, $this->request('GET', '/policies/cancellation/0')['body']['config'] ?? null);

        // Upserting again on the same (type, service_id) replaces, not duplicates.
        $replaced = ['notice_hours' => null, 'refund' => []];
        $this->request('PUT', '/policies/cancellation/0', $replaced);
        self::assertSame($replaced, $this->request('GET', '/policies/cancellation/0')['body']['config']);
        self::assertSame(
            1,
            (int) $this->realDb()->getVar(
                'SELECT COUNT(*) FROM %i WHERE type = %s AND service_id = 0',
                Tables::name('policies'),
                'cancellation'
            )
        );

        self::assertSame(204, $this->request('DELETE', '/policies/cancellation/0')['status']);
        self::assertSame(['config' => null], $this->request('GET', '/policies/cancellation/0')['body']);
    }

    public function testAReschedulePolicyIsUpsertedForAService(): void
    {
        $this->logInAs('administrator');
        $service = $this->service();

        $saved = $this->request('PUT', "/policies/reschedule/{$service}", ['notice_hours' => 12, 'max_times' => 2]);

        self::assertSame(
            [200, ['notice_hours' => 12, 'max_times' => 2]],
            [$saved['status'], $saved['body']['config'] ?? null]
        );
        // The global level (0) is untouched by a service's own policy.
        self::assertSame(['config' => null], $this->request('GET', '/policies/reschedule/0')['body']);
    }

    public function testAnUnknownServiceIs404(): void
    {
        $this->logInAs('administrator');

        $response = $this->request('GET', '/policies/cancellation/999999');

        self::assertSame([404, 'service_not_found'], [$response['status'], $response['body']['code'] ?? null]);
    }

    /**
     * The REST schema (0 to 100) already covers what RefundTier's own
     * constructor checks, so an out-of-range tier never reaches the
     * Domain: WordPress's own schema validation refuses it first, with
     * 400 rest_invalid_param (architecture §9), not a 422 domain code.
     */
    public function testATierOutOfSchemaRangeIs400(): void
    {
        $this->logInAs('administrator');

        $response = $this->request('PUT', '/policies/cancellation/0', [
            'refund' => [['hours' => 24, 'percent' => 150]],
        ]);

        self::assertSame(
            [400, 'rest_invalid_param'],
            [$response['status'], $response['body']['code'] ?? null]
        );
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

    private function logInAs(string $role): void
    {
        $id = \wp_insert_user([
            'user_login' => 'policy_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'policy_' . $role . \count($this->users) . '@example.com',
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
