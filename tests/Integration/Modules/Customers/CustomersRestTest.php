<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Customers;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * The admin customer API through the real REST server, as the booted plugin
 * registered it: the whole life of a customer, the Persian search, one
 * customer per phone number, and the link to a WordPress account.
 */
final class CustomersRestTest extends TestCase
{
    use RealDatabase;

    /** @var list<int> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('customers'));
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
        $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('customers'));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    public function testTheModuleGaveAdministratorsTheCustomersCapability(): void
    {
        self::assertTrue(\get_role('administrator')?->has_cap(Caps::name('manage_customers')));
    }

    public function testOnlyUsersWithTheCapabilityReachTheRoutes(): void
    {
        $guest = $this->request('GET', '/customers');
        $this->logInAs('editor');
        $editor = $this->request('POST', '/customers', ['first_name' => 'Ali', 'phone' => '09121234567']);

        self::assertSame(
            [401, 403],
            [$guest['status'], $editor['status']]
        );
        self::assertSame('0', $this->realDb()->getVar('SELECT COUNT(*) FROM %i', Tables::name('customers')));
    }

    public function testACustomerGoesThroughTheirWholeLife(): void
    {
        $this->logInAs('administrator');

        $created = $this->request('POST', '/customers', [
            'first_name' => ' علی ',
            'last_name' => 'کریمی',
            'phone' => '۰۹۱۲ ۱۲۳ ۴۵۶۷',
            'email' => 'Ali@Example.COM',
            'birth_date' => '1990-05-01',
            'tags' => ['VIP', 'VIP', 'نوبت اول'],
        ]);
        $id = $created['body']['id'] ?? null;
        self::assertIsInt($id, (string) \wp_json_encode($created['body']));
        self::assertSame(201, $created['status']);
        $uuid = $created['body']['uuid'] ?? null;
        self::assertIsString($uuid);
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uuid);
        self::assertSame(
            ['علی', '+989121234567', 'Ali@example.com', '1990-05-01', ['VIP', 'نوبت اول'], 'active'],
            self::pick($created['body'], ['first_name', 'phone', 'email', 'birth_date', 'tags', 'status'])
        );

        $updated = $this->request('PUT', "/customers/{$id}", [
            'first_name' => 'Ali',
            'phone' => '09121234567',
            'status' => 'blocked',
            'uuid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ]);
        // PUT replaces the customer, but the uuid is never the request's.
        self::assertSame(
            [200, 'Ali', '', null, 'blocked', $created['body']['uuid']],
            [
                $updated['status'],
                ...self::pick($updated['body'], ['first_name', 'last_name', 'email', 'status', 'uuid']),
            ]
        );

        self::assertSame(204, $this->request('DELETE', "/customers/{$id}")['status']);
        $gone = $this->request('GET', "/customers/{$id}");
        self::assertSame([404, 'customer_not_found'], [$gone['status'], $gone['body']['code'] ?? null]);
        // Soft deleted, and the number is free for a new sign-up.
        self::assertSame(
            [['phone' => null]],
            $this->realDb()->getResults('SELECT phone FROM %i WHERE id = %d', Tables::name('customers'), $id)
        );
        self::assertSame(
            201,
            $this->request('POST', '/customers', ['first_name' => 'Ali', 'phone' => '09121234567'])['status']
        );
    }

    public function testAPhoneNumberBelongsToOneCustomer(): void
    {
        $this->logInAs('administrator');
        $this->request('POST', '/customers', ['first_name' => 'Ali', 'phone' => '09121234567']);

        $again = $this->request('POST', '/customers', ['first_name' => 'Reza', 'phone' => '+98 912 123 4567']);

        self::assertSame([409, 'phone_taken'], [$again['status'], $again['body']['code'] ?? null]);
    }

    public function testInvalidInputIsRefused(): void
    {
        $this->logInAs('administrator');

        $noName = $this->request('POST', '/customers', ['phone' => '09121234567']);
        $badPhone = $this->request('POST', '/customers', ['first_name' => 'Ali', 'phone' => '123']);
        $badUser = $this->request('POST', '/customers', [
            'first_name' => 'Ali',
            'phone' => '09121234567',
            'wp_user_id' => 999_999,
        ]);

        self::assertSame(
            [[422, 'invalid_name'], [422, 'invalid_phone'], [422, 'unknown_user']],
            [
                [$noName['status'], $noName['body']['code'] ?? null],
                [$badPhone['status'], $badPhone['body']['code'] ?? null],
                [$badUser['status'], $badUser['body']['code'] ?? null],
            ]
        );
    }

    /**
     * Arabic letters, Persian digits and part of a number all find the
     * customer; LIKE wildcards in the query are literal.
     */
    public function testTheSearchNormalizesPersianText(): void
    {
        $this->logInAs('administrator');
        $this->request(
            'POST',
            '/customers',
            ['first_name' => 'علی', 'last_name' => 'کریمی', 'phone' => '09121234567']
        );
        $this->request('POST', '/customers', ['first_name' => 'Sara', 'phone' => '09350000000', 'email' => 's@x.ir']);

        $found = [];
        // Arabic ya, and Arabic kaf and ya: typed on an Arabic keyboard.
        $arabic = ["\u{0639}\u{0644}\u{064A}", "\u{0643}\u{0631}\u{064A}\u{0645}\u{064A}"];
        foreach ([...$arabic, '۰۹۱۲۱۲۳', 'S@X', 'sara', '%', ''] as $query) {
            $response = $this->request('GET', '/customers', ['search' => $query]);
            $found[] = \array_map(
                static fn (mixed $customer): mixed => \is_array($customer) ? $customer['first_name'] : null,
                $response['body']
            );
        }

        self::assertSame([['علی'], ['علی'], ['علی'], ['Sara'], ['Sara'], [], ['Sara', 'علی']], $found);
        self::assertSame(
            '2',
            $this->request('GET', '/customers', ['per_page' => 1])['headers']['X-WP-Total'] ?? null
        );
    }

    public function testTheListCanBeFilteredByStatus(): void
    {
        $this->logInAs('administrator');
        $this->request(
            'POST',
            '/customers',
            ['first_name' => 'Active', 'phone' => '09121234567']
        );
        $blocked = $this->request(
            'POST',
            '/customers',
            ['first_name' => 'Blocked', 'phone' => '09350000000', 'status' => 'blocked']
        )['body']['id'] ?? null;

        $active = $this->request('GET', '/customers', ['status' => 'active']);
        $inactive = $this->request('GET', '/customers', ['status' => 'blocked']);

        self::assertSame(['Active'], \array_column($active['body'], 'first_name'));
        self::assertSame(['Blocked'], \array_column($inactive['body'], 'first_name'));
        self::assertSame([$blocked], \array_column($inactive['body'], 'id'));
    }

    public function testDeletingTheWordPressAccountUnlinksTheCustomer(): void
    {
        $this->logInAs('administrator');
        $this->logInAs('subscriber');
        $account = (int) \end($this->users);
        \wp_set_current_user($this->users[0]);
        $created = $this->request('POST', '/customers', [
            'first_name' => 'Ali',
            'phone' => '09121234567',
            'wp_user_id' => $account,
        ]);
        $id = $created['body']['id'] ?? null;
        self::assertIsInt($id);
        $taken = $this->request('POST', '/customers', [
            'first_name' => 'Reza',
            'phone' => '09122222222',
            'wp_user_id' => $account,
        ]);

        require_once \ABSPATH . 'wp-admin/includes/user.php';
        \wp_delete_user($account);
        \array_pop($this->users);

        self::assertSame([409, 'user_taken'], [$taken['status'], $taken['body']['code'] ?? null]);
        $after = $this->request('GET', "/customers/{$id}")['body'];
        self::assertSame([true, null], [\array_key_exists('wp_user_id', $after), $after['wp_user_id']]);
    }

    private function logInAs(string $role): void
    {
        $id = \wp_insert_user([
            'user_login' => 'customers_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'customers_' . $role . \count($this->users) . '@example.com',
            'role' => $role,
        ]);
        self::assertIsInt($id);
        $this->users[] = $id;
        \wp_set_current_user($id);
    }

    /**
     * @param array<mixed> $item
     * @param list<string> $keys
     * @return list<mixed>
     */
    private static function pick(array $item, array $keys): array
    {
        return \array_map(static fn (string $key): mixed => $item[$key] ?? null, $keys);
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
