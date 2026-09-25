<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Catalog;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * The admin catalog API through the real REST server, as the booted plugin
 * registered it.
 */
final class CatalogRestTest extends TestCase
{
    use CatalogTables;
    use RealDatabase;

    /** @var list<int> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->realDb();
        // A fresh server, so rest_api_init registers the routes again.
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

    public function testTheModuleGaveAdministratorsTheCatalogCapability(): void
    {
        self::assertTrue(\get_role('administrator')?->has_cap(Caps::name('manage_catalog')));
    }

    public function testAGuestIsAskedToLogIn(): void
    {
        $response = $this->request('GET', '/locations');

        self::assertSame([401, 'rest_forbidden'], [$response['status'], $response['body']['code'] ?? null]);
    }

    /**
     * WordPress checks the parameters before the permission, so each write
     * sends a valid item: the refusal must come from the permission.
     *
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function routes(): iterable
    {
        $items = [
            'locations' => ['name' => 'Main', 'timezone' => 'Asia/Tehran'],
            'staff' => ['name' => 'Dr. Karimi', 'color' => '#112233'],
            'resources' => ['name' => 'Room 1', 'group_key' => 'room'],
            'service-categories' => ['name' => 'Dental', 'color' => '#112233'],
            'services' => ['name' => 'Checkup', 'variants' => [self::variant()]],
            'extras' => ['name' => 'X-ray', 'price' => self::rial(500_000), 'duration_min' => 10],
        ];
        foreach ($items as $base => $item) {
            yield "list {$base}" => ['GET', "/{$base}", []];
            yield "read {$base}" => ['GET', "/{$base}/1", []];
            yield "create {$base}" => ['POST', "/{$base}", $item];
            yield "update {$base}" => ['PUT', "/{$base}/1", $item];
            yield "delete {$base}" => ['DELETE', "/{$base}/1", []];
        }
    }

    /**
     * @dataProvider routes
     * @param array<string, mixed> $item
     */
    public function testAUserWithoutTheCapabilityIsForbiddenOnEveryRoute(
        string $method,
        string $path,
        array $item,
    ): void {
        $this->logInAs('editor');

        $response = $this->request($method, $path, $item);

        self::assertSame([403, 'rest_forbidden'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testALocationGoesThroughItsWholeLife(): void
    {
        $this->logInAs('administrator');

        $created = $this->request('POST', '/locations', [
            'name' => ' Main branch ',
            'timezone' => 'Asia/Tehran',
            'phone' => '۰۲۱ ۱۲۳۴ ۵۶۷۸',
            'holiday_calendar' => 'ir',
        ]);
        $id = $created['body']['id'] ?? null;
        self::assertIsInt($id);
        self::assertSame(201, $created['status']);
        self::assertSame(
            ['Main branch', 'Asia/Tehran', '+982112345678', 'ir', 'active', 0, ''],
            self::pick($created['body'], ['name', 'timezone', 'phone', 'holiday_calendar', 'status', 'sort', 'address'])
        );

        $updated = $this->request('PUT', "/locations/{$id}", [
            'name' => 'North branch',
            'timezone' => 'Asia/Tehran',
            'status' => 'inactive',
        ]);
        // PUT replaces the item: the phone left out is gone.
        self::assertSame(
            [200, 'North branch', 'inactive', null],
            [$updated['status'], ...self::pick($updated['body'], ['name', 'status', 'phone'])]
        );
        self::assertSame('North branch', $this->request('GET', "/locations/{$id}")['body']['name'] ?? null);

        self::assertSame(204, $this->request('DELETE', "/locations/{$id}")['status']);
        $gone = $this->request('GET', "/locations/{$id}");
        self::assertSame([404, 'location_not_found'], [$gone['status'], $gone['body']['code'] ?? null]);
        // Soft deleted: the row stays for the appointments that point at it.
        self::assertNotNull($this->realDb()->getVar(
            'SELECT deleted_at FROM %i WHERE id = %d',
            Tables::name('locations'),
            $id
        ));
    }

    public function testAListIsPagedWithTheTotalInTheHeaders(): void
    {
        $this->logInAs('administrator');
        foreach (['C', 'A', 'B'] as $sort => $name) {
            $this->request('POST', '/service-categories', ['name' => $name, 'color' => '#112233', 'sort' => $sort]);
        }

        $page = $this->request('GET', '/service-categories', ['page' => 2, 'per_page' => 2]);

        self::assertSame(200, $page['status']);
        self::assertSame(
            ['3', '2'],
            [$page['headers']['X-WP-Total'] ?? null, $page['headers']['X-WP-TotalPages'] ?? null]
        );
        self::assertSame(['B'], \array_column($page['body'], 'name'));
        self::assertSame(400, $this->request('GET', '/service-categories', ['per_page' => 101])['status']);
    }

    public function testAWrongTypeIs400AndABrokenRuleIs422WithItsCode(): void
    {
        $this->logInAs('administrator');

        $wrongType = $this->request('POST', '/locations', ['name' => 'Main', 'timezone' => 5]);
        $badZone = $this->request('POST', '/locations', ['name' => 'Main', 'timezone' => 'Mars/Olympus']);
        $offset = $this->request('POST', '/locations', ['name' => 'Main', 'timezone' => '+03:30']);

        self::assertSame(
            [[400, 'rest_invalid_param'], [422, 'invalid_timezone'], [422, 'invalid_timezone']],
            [
                [$wrongType['status'], $wrongType['body']['code'] ?? null],
                [$badZone['status'], $badZone['body']['code'] ?? null],
                [$offset['status'], $offset['body']['code'] ?? null],
            ]
        );
    }

    public function testStaffKeepToKnownLocationsAndGetASearchName(): void
    {
        $this->logInAs('administrator');
        $unknown = $this->request(
            'POST',
            '/staff',
            ['name' => 'Dr. Karimi', 'color' => '#112233', 'location_id' => 99]
        );
        $location = $this->location();

        $staff = $this->request('POST', '/staff', [
            'name' => "\u{0639}\u{0644}\u{064A} \u{0643}\u{0631}\u{064A}\u{0645}\u{064A}",
            'color' => '#AABBCC',
            'location_id' => $location,
            'email' => 'Karimi@Example.COM',
            'wp_user_id' => null,
        ]);

        self::assertSame([422, 'unknown_location'], [$unknown['status'], $unknown['body']['code'] ?? null]);
        self::assertSame(201, $staff['status']);
        self::assertSame(
            ['#aabbcc', $location, 'Karimi@example.com'],
            self::pick($staff['body'], ['color', 'location_id', 'email'])
        );
        self::assertSame('علی کریمی', $this->realDb()->getVar(
            'SELECT search_name FROM %i WHERE id = %d',
            Tables::name('staff'),
            self::id($staff)
        ));
        $userless = $this->request('POST', '/staff', ['name' => 'X', 'color' => '#112233', 'wp_user_id' => 999999]);
        self::assertSame('unknown_user', $userless['body']['code'] ?? null);
    }

    public function testALocationWithStaffIsNotDeleted(): void
    {
        $this->logInAs('administrator');
        $location = $this->location();
        $this->request('POST', '/resources', ['name' => 'Room 1', 'group_key' => 'room', 'location_id' => $location]);

        $response = $this->request('DELETE', "/locations/{$location}");

        self::assertSame([422, 'location_in_use'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testAServiceIsSavedWholeAndItsVariantsKeepTheirIds(): void
    {
        $this->logInAs('administrator');
        $staff = $this->staffMember();
        $created = $this->request('POST', '/services', [
            'name' => 'Consultation',
            'variants' => [
                ['label' => '30 min', 'is_default' => true] + self::variant(30, 1_000_000),
                ['label' => '60 min', 'is_default' => false] + self::variant(60, 25_000_000_000),
            ],
            'resources' => [['group_key' => 'room']],
        ]);
        self::assertSame(201, $created['status'], (string) \wp_json_encode($created['body']));
        $id = self::id($created);
        /** @var list<array{id: int, price: array{amount: int}}> $variants */
        $variants = $created['body']['variants'] ?? [];
        [$short, $long] = [$variants[0]['id'], $variants[1]['id']];
        self::assertSame(25_000_000_000, $variants[1]['price']['amount']);

        // Keep the short one, drop the long one, add a 45-minute one, and give
        // the staff member their own price for the short one.
        $updated = $this->request('PUT', "/services/{$id}", [
            'name' => 'Consultation',
            'variants' => [
                ['id' => $short, 'label' => '30 min', 'is_default' => true] + self::variant(30, 1_000_000),
                ['label' => '45 min', 'is_default' => false] + self::variant(45, 1_500_000),
            ],
            'staff' => [['staff_id' => $staff, 'variant_id' => $short, 'price' => self::rial(1_200_000)]],
        ]);

        self::assertSame(200, $updated['status'], (string) \wp_json_encode($updated['body']));
        /** @var list<array{id: int, label: string}> $kept */
        $kept = $updated['body']['variants'] ?? [];
        self::assertSame([$short, '30 min'], [$kept[0]['id'], $kept[0]['label']]);
        self::assertSame('45 min', $kept[1]['label']);
        self::assertNotContains($long, \array_column($kept, 'id'));
        self::assertSame(
            [[
                'staff_id' => $staff,
                'variant_id' => $short,
                'price' => self::rial(1_200_000),
                'duration_min' => null,
            ]],
            $updated['body']['staff'] ?? null
        );
        self::assertSame([], $updated['body']['resources'] ?? null);
        self::assertNotNull($this->realDb()->getVar(
            'SELECT deleted_at FROM %i WHERE id = %d',
            Tables::name('service_variants'),
            $long
        ));
    }

    /**
     * The admin UI sends back what it read; a deleted category or staff
     * member must not make that fail.
     */
    public function testAServiceStillSavesAsReadAfterItsCategoryAndStaffAreDeleted(): void
    {
        $this->logInAs('administrator');
        $category = self::id($this->request('POST', '/service-categories', ['name' => 'Dental', 'color' => '#112233']));
        $staff = $this->staffMember();
        $created = $this->request('POST', '/services', [
            'name' => 'Checkup',
            'category_id' => $category,
            'variants' => [self::variant()],
            'staff' => [['staff_id' => $staff]],
        ]);
        $id = self::id($created);
        $this->request('DELETE', "/service-categories/{$category}");
        $this->request('DELETE', "/staff/{$staff}");

        $read = $this->request('GET', "/services/{$id}");
        $saved = $this->request('PUT', "/services/{$id}", $read['body']);

        self::assertSame([null, []], self::pick($read['body'], ['category_id', 'staff']));
        self::assertSame(200, $saved['status'], (string) \wp_json_encode($saved['body']));
    }

    public function testADeletedServiceTakesItsExtrasWithIt(): void
    {
        $this->logInAs('administrator');
        $service = self::id($this->request('POST', '/services', ['name' => 'A', 'variants' => [self::variant()]]));
        $extra = self::id($this->request('POST', '/extras', [
            'name' => 'X-ray',
            'price' => self::rial(500_000),
            'duration_min' => 10,
            'service_id' => $service,
        ]));

        $this->request('DELETE', "/services/{$service}");

        self::assertSame(404, $this->request('GET', "/extras/{$extra}")['status']);
    }

    public function testAServiceCannotTakeAnotherServicesVariant(): void
    {
        $this->logInAs('administrator');
        $first = $this->request('POST', '/services', ['name' => 'A', 'variants' => [self::variant()]]);
        $second = $this->request('POST', '/services', ['name' => 'B', 'variants' => [self::variant()]]);
        /** @var list<array{id: int}> $firstVariants */
        $firstVariants = $first['body']['variants'] ?? [];
        $secondId = self::id($second);

        $response = $this->request('PUT', "/services/{$secondId}", [
            'name' => 'B',
            'variants' => [['id' => $firstVariants[0]['id']] + self::variant()],
        ]);

        self::assertSame([422, 'unknown_variant'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testAnUnknownStaffMemberOrCategoryIsRefused(): void
    {
        $this->logInAs('administrator');

        $staff = $this->request('POST', '/services', [
            'name' => 'A',
            'variants' => [self::variant()],
            'staff' => [['staff_id' => 404]],
        ]);
        $category = $this->request(
            'POST',
            '/services',
            ['name' => 'A', 'variants' => [self::variant()], 'category_id' => 404]
        );

        self::assertSame(
            ['unknown_staff', 'unknown_category'],
            [$staff['body']['code'] ?? null, $category['body']['code'] ?? null]
        );
    }

    public function testResourcesAndExtrasAreStored(): void
    {
        $this->logInAs('administrator');
        $created = $this->request('POST', '/services', ['name' => 'A', 'variants' => [self::variant()]]);
        $service = self::id($created);

        $resource = $this->request('POST', '/resources', ['name' => 'Chair', 'group_key' => 'Chair', 'capacity' => 2]);
        $extra = $this->request('POST', '/extras', [
            'name' => 'X-ray',
            'price' => self::rial(500_000),
            'duration_min' => 10,
            'service_id' => $service,
        ]);
        $orphan = $this->request('POST', '/extras', [
            'name' => 'X-ray',
            'price' => self::rial(500_000),
            'duration_min' => 10,
            'service_id' => 404,
        ]);

        self::assertSame(['chair', 2], self::pick($resource['body'], ['group_key', 'capacity']));
        self::assertSame(
            [self::rial(500_000), $service, 1],
            self::pick($extra['body'], ['price', 'service_id', 'max_qty'])
        );
        self::assertSame('unknown_service', $orphan['body']['code'] ?? null);
    }

    private function location(): int
    {
        $created = $this->request('POST', '/locations', ['name' => 'Main', 'timezone' => 'Asia/Tehran']);

        return self::id($created);
    }

    private function staffMember(): int
    {
        $created = $this->request('POST', '/staff', ['name' => 'Dr. Karimi', 'color' => '#112233']);

        return self::id($created);
    }

    /**
     * @return array<string, mixed>
     */
    private static function variant(int $minutes = 30, int $price = 1_000_000): array
    {
        return ['duration_min' => $minutes, 'price' => self::rial($price), 'is_default' => true];
    }

    /**
     * @return array{amount: int, currency: string}
     */
    private static function rial(int $amount): array
    {
        return ['amount' => $amount, 'currency' => 'IRR'];
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

    /**
     * @param array{status: int, body: array<mixed>, headers: array<string, string>} $response
     */
    private static function id(array $response): int
    {
        $id = $response['body']['id'] ?? null;
        self::assertIsInt($id, (string) \wp_json_encode($response['body']));

        return $id;
    }

    private function logInAs(string $role): void
    {
        $id = \wp_insert_user([
            'user_login' => 'catalog_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'catalog_' . $role . \count($this->users) . '@example.com',
            'role' => $role,
        ]);
        self::assertIsInt($id);
        $this->users[] = $id;
        \wp_set_current_user($id);
    }

    /**
     * @param array<string, mixed> $params A JSON body, or the query for GET.
     * @return array{status: int, body: array<mixed>, headers: array<string, string>}
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
        // As the route returned it; an error is already the envelope array.
        $body = $response->get_data();
        /** @var array<string, string> $headers */
        $headers = $response->get_headers();

        return [
            'status' => $response->get_status(),
            'body' => \is_array($body) ? $body : [],
            'headers' => $headers,
        ];
    }
}
