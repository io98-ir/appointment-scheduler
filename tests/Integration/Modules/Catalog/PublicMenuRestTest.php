<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Catalog;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * GET /catalog without login (T4.1): the bookable menu, and nothing that is
 * inactive, deleted, or served by nobody.
 */
final class PublicMenuRestTest extends TestCase
{
    use CatalogTables;
    use RealDatabase;

    /** @var list<int> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->realDb();
        $this->emptyCatalogTables();
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
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    public function testAGuestSeesTheBookableMenu(): void
    {
        $this->logInAsAdministrator();
        $location = $this->create('/locations', ['name' => 'Main', 'timezone' => 'Asia/Tehran']);
        $staff = $this->create('/staff', ['name' => 'Sara', 'color' => '#336699', 'location_id' => $location]);
        $service = $this->service('Haircut', [['staff_id' => $staff]]);
        \wp_set_current_user(0);

        $response = $this->get();
        $menu = $response['body'];
        $services = self::rows($menu['services'] ?? null);

        self::assertSame(200, $response['status'], (string) \wp_json_encode($menu));
        self::assertSame(['Main'], \array_column(self::rows($menu['locations'] ?? null), 'name'));
        self::assertSame([$service], \array_column($services, 'id'));
        self::assertSame(['30 min'], \array_column(self::rows($services[0]['variants'] ?? null), 'label'));
        $assigned = self::rows($services[0]['staff'] ?? null)[0] ?? [];
        self::assertSame(
            [$staff, 'Sara', $location, 'every variant'],
            [
                $assigned['staff_id'] ?? 0,
                $assigned['name'] ?? '',
                $assigned['location_id'] ?? 0,
                ($assigned['variant_id'] ?? null) ?? 'every variant',
            ]
        );
    }

    public function testInactiveAndUnstaffedItemsAreLeftOut(): void
    {
        $this->logInAsAdministrator();
        $open = $this->create('/locations', ['name' => 'Open', 'timezone' => 'Asia/Tehran']);
        $closed = $this->create(
            '/locations',
            ['name' => 'Closed', 'timezone' => 'Asia/Tehran', 'status' => 'inactive']
        );
        $sara = $this->create('/staff', ['name' => 'Sara', 'color' => '#336699', 'location_id' => $open]);
        $ali = $this->create('/staff', ['name' => 'Ali', 'color' => '#993366', 'location_id' => $closed]);
        $away = $this->create('/staff', ['name' => 'Away', 'color' => '#999999', 'status' => 'inactive']);
        $shown = $this->service('Shown', [['staff_id' => $sara], ['staff_id' => $ali], ['staff_id' => $away]]);
        $this->service('Nobody', []);
        $this->service('Only closed', [['staff_id' => $ali]]);
        $this->service('Hidden', [['staff_id' => $sara]], 'inactive');
        \wp_set_current_user(0);

        $menu = $this->get()['body'];
        $services = self::rows($menu['services'] ?? null);

        self::assertSame(['Open'], \array_column(self::rows($menu['locations'] ?? null), 'name'));
        self::assertSame([$shown], \array_column($services, 'id'));
        self::assertSame([$sara], \array_column(self::rows($services[0]['staff'] ?? null), 'staff_id'));
    }

    /**
     * @return list<array<mixed>>
     */
    private static function rows(mixed $value): array
    {
        return \is_array($value) ? \array_values(\array_filter($value, 'is_array')) : [];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function create(string $path, array $body): int
    {
        $created = $this->send('POST', $path, $body);
        $id = $created['body']['id'] ?? null;
        self::assertIsInt($id, (string) \wp_json_encode($created['body']));

        return $id;
    }

    /**
     * @param list<array{staff_id: int}> $staff
     */
    private function service(string $name, array $staff, string $status = 'active'): int
    {
        return $this->create('/services', [
            'name' => $name,
            'status' => $status,
            'variants' => [[
                'label' => '30 min',
                'is_default' => true,
                'duration_min' => 30,
                'price' => ['amount' => 1_000_000, 'currency' => 'IRR'],
            ]],
            'staff' => $staff,
        ]);
    }

    private function logInAsAdministrator(): void
    {
        $id = \wp_insert_user([
            'user_login' => 'menu_admin_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'menu_admin' . \count($this->users) . '@example.com',
            'role' => 'administrator',
        ]);
        self::assertIsInt($id);
        $this->users[] = $id;
        \wp_set_current_user($id);
    }

    /**
     * @return array{status: int, body: array<mixed>}
     */
    private function get(): array
    {
        return $this->send('GET', '/catalog', []);
    }

    /**
     * @param array<string, mixed> $params
     * @return array{status: int, body: array<mixed>}
     */
    private function send(string $method, string $path, array $params): array
    {
        $request = new \WP_REST_Request($method, '/' . Identity::REST_NAMESPACE . $path);
        if ('GET' !== $method) {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body((string) \wp_json_encode($params));
        }
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
    }
}
