<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Admin;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Modules\Admin\Domain\HealthEvaluator;

/**
 * The System Status API and the Site Health tests through WordPress (T6.2):
 * who may read them, the shape of the report, and that turning a module off
 * is stored, validated and reversible.
 */
final class StatusRestTest extends TestCase
{
    /** @var list<int> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
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
        \delete_option(Options::key('settings_modules'));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    public function testTheModuleGaveAdministratorsTheSystemCapability(): void
    {
        self::assertTrue(\get_role('administrator')?->has_cap(Caps::name('manage_system')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routes(): iterable
    {
        yield 'read status' => ['GET', '/status'];
        yield 'turn a module off' => ['PUT', '/modules/widget'];
    }

    /**
     * @dataProvider routes
     */
    public function testAUserWithoutTheCapabilityIsForbidden(string $method, string $path): void
    {
        $this->logInAs('editor');

        $response = $this->request($method, $path, ['enabled' => false]);

        self::assertSame([403, 'rest_forbidden'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testTheReportHasEveryPart(): void
    {
        $this->logInAs('administrator');

        $response = $this->request('GET', '/status');
        $body = $response['body'];

        self::assertSame(200, $response['status']);
        self::assertSame(
            ['versions', 'checks', 'queue', 'schema', 'modules', 'errors'],
            \array_keys($body)
        );
        $checks = $body['checks'];
        self::assertIsArray($checks);
        self::assertSame(HealthEvaluator::IDS, \array_column($checks, 'id'));
        foreach ($checks as $check) {
            self::assertIsArray($check);
            self::assertContains($check['status'], ['good', 'recommended', 'critical']);
            self::assertNotSame('', $check['label']);
        }
        $versions = $body['versions'];
        self::assertIsArray($versions);
        self::assertSame(\PHP_VERSION, $versions['php']);
        self::assertSame(\get_bloginfo('version'), $versions['wordpress']);
        self::assertSame(
            ['pending', 'late', 'failed'],
            \array_keys(\is_array($body['queue']) ? $body['queue'] : [])
        );
    }

    public function testInnoDbIsSeenOnATestSite(): void
    {
        $this->logInAs('administrator');

        $checks = $this->request('GET', '/status')['body']['checks'];

        self::assertIsArray($checks);
        self::assertSame('good', $checks[0]['status'], 'The plugin tables are InnoDB (checked when created).');
    }

    public function testModulesListWhichOnesMayBeTurnedOff(): void
    {
        $this->logInAs('administrator');

        $modules = $this->request('GET', '/status')['body']['modules'];

        self::assertIsArray($modules);
        $switchable = [];
        foreach ($modules as $module) {
            self::assertIsArray($module);
            if (true === $module['switchable']) {
                $switchable[] = $module['id'];
            }
        }
        self::assertContains('notifications', $switchable);
        self::assertContains('widget', $switchable);
        self::assertNotContains('booking', $switchable);
    }

    public function testAModuleIsTurnedOffAndOnAgain(): void
    {
        $this->logInAs('administrator');

        $off = $this->request('PUT', '/modules/widget', ['enabled' => false]);
        self::assertSame(200, $off['status']);
        self::assertFalse(self::enabled($off['body'], 'widget'));
        self::assertTrue(self::enabled($off['body'], 'notifications'));
        $stored = \get_option(Options::key('settings_modules'));
        self::assertSame(['disabled' => ['widget']], $stored);

        $on = $this->request('PUT', '/modules/widget', ['enabled' => true]);
        self::assertTrue(self::enabled($on['body'], 'widget'));
        self::assertSame(['disabled' => []], \get_option(Options::key('settings_modules')));
    }

    public function testACoreOrUnknownModuleCannotBeTurnedOff(): void
    {
        $this->logInAs('administrator');

        foreach (['booking', 'admin', 'nothing'] as $id) {
            $response = $this->request('PUT', '/modules/' . $id, ['enabled' => false]);

            self::assertSame([422, 'module_not_switchable'], [$response['status'], $response['body']['code'] ?? null]);
        }
        self::assertFalse(\get_option(Options::key('settings_modules')));
    }

    public function testSiteHealthGetsOneTestPerCheck(): void
    {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's hook.
        $tests = \apply_filters('site_status_tests', ['direct' => [], 'async' => []]);

        self::assertIsArray($tests);
        $direct = $tests['direct'];
        self::assertIsArray($direct);
        foreach (HealthEvaluator::IDS as $id) {
            $key = Identity::PREFIX . '_' . $id;
            self::assertArrayHasKey($key, $direct);
            $result = ($direct[$key]['test'])();
            self::assertSame($key, $result['test']);
            self::assertContains($result['status'], ['good', 'recommended', 'critical']);
            self::assertSame(Identity::NAME, $result['badge']['label']);
        }
    }

    /**
     * @param array<mixed> $body A /modules response.
     */
    private static function enabled(array $body, string $id): ?bool
    {
        $modules = $body['modules'] ?? null;
        foreach (\is_array($modules) ? $modules : [] as $module) {
            if (\is_array($module) && $module['id'] === $id) {
                return (bool) $module['enabled'];
            }
        }

        return null;
    }

    private function logInAs(string $role): void
    {
        $id = \wp_insert_user([
            'user_login' => 'status_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'status_' . $role . \count($this->users) . '@example.com',
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
