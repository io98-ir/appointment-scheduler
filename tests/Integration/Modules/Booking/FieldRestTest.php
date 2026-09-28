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
 * The admin custom-fields API through the real REST server
 * (implementation-notes §4.13): flat CRUD on the fields table, and the
 * duplicate-key / show_if checks FieldSetValidator runs against the
 * "available set" a field would join at booking time.
 */
final class FieldRestTest extends TestCase
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
        $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('fields'));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    public function testTheModuleGaveAdministratorsTheBookingCapability(): void
    {
        self::assertTrue(\get_role('administrator')?->has_cap(Caps::name('manage_bookings')));
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function routes(): iterable
    {
        $body = ['scope' => 'global', 'field_key' => 'x', 'type' => 'text', 'label' => 'X'];
        yield 'list' => ['GET', '/fields', ['scope' => 'global']];
        yield 'create' => ['POST', '/fields', $body];
        yield 'update' => ['PUT', '/fields/1', $body];
        yield 'delete' => ['DELETE', '/fields/1', []];
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

    public function testAGlobalFieldGoesThroughItsWholeLife(): void
    {
        $this->logInAs('administrator');

        $created = $this->request('POST', '/fields', [
            'scope' => 'global',
            'field_key' => 'allergies',
            'type' => 'textarea',
            'label' => 'Allergies',
            'required' => false,
            'sort' => 1,
        ]);
        $id = $created['body']['id'] ?? null;
        self::assertIsInt($id, (string) \wp_json_encode($created['body']));
        self::assertSame(
            [201, 'global', null, 'allergies', 'textarea', 'Allergies', false],
            [
                $created['status'],
                ...self::pick($created['body'], ['scope', 'service_id', 'field_key', 'type', 'label', 'required']),
            ]
        );

        $listed = $this->request('GET', '/fields', ['scope' => 'global']);
        self::assertSame([$id], \array_column($listed['body'], 'id'));

        $updated = $this->request('PUT', "/fields/{$id}", [
            'scope' => 'global',
            'field_key' => 'allergies',
            'type' => 'textarea',
            'label' => 'Allergies (optional)',
            'required' => false,
            'sort' => 2,
        ]);
        self::assertSame([200, 'Allergies (optional)'], [$updated['status'], $updated['body']['label'] ?? null]);

        self::assertSame(204, $this->request('DELETE', "/fields/{$id}")['status']);
        self::assertSame([], $this->request('GET', '/fields', ['scope' => 'global'])['body']);
        $gone = $this->request('DELETE', "/fields/{$id}");
        self::assertSame([404, 'field_not_found'], [$gone['status'], $gone['body']['code'] ?? null]);
    }

    public function testASelectFieldNeedsOptions(): void
    {
        $this->logInAs('administrator');

        $response = $this->request('POST', '/fields', [
            'scope' => 'global',
            'field_key' => 'source',
            'type' => 'select',
            'label' => 'How did you hear about us?',
        ]);

        self::assertSame([422, 'invalid_field'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testAServiceFieldNeedsAStoredService(): void
    {
        $this->logInAs('administrator');

        $response = $this->request('POST', '/fields', [
            'scope' => 'service',
            'service_id' => 999999,
            'field_key' => 'x',
            'type' => 'text',
            'label' => 'X',
        ]);

        self::assertSame([404, 'service_not_found'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testAServiceListIsItsOwnOnlyNotTheGlobals(): void
    {
        $this->logInAs('administrator');
        $service = $this->service();
        $this->request('POST', '/fields', ['scope' => 'global', 'field_key' => 'g', 'type' => 'text', 'label' => 'G']);
        $own = $this->request('POST', '/fields', [
            'scope' => 'service',
            'service_id' => $service,
            'field_key' => 's',
            'type' => 'text',
            'label' => 'S',
        ]);

        $listed = $this->request('GET', '/fields', ['scope' => 'service', 'service_id' => $service]);

        self::assertSame([$own['body']['id']], \array_column($listed['body'], 'id'));
    }

    public function testADuplicateKeyInTheAvailableSetIsRejected(): void
    {
        $this->logInAs('administrator');
        $service = $this->service();
        $this->request('POST', '/fields', [
            'scope' => 'global',
            'field_key' => 'note',
            'type' => 'text',
            'label' => 'Note',
        ]);

        $response = $this->request('POST', '/fields', [
            'scope' => 'service',
            'service_id' => $service,
            'field_key' => 'note',
            'type' => 'text',
            'label' => 'Also note',
        ]);

        self::assertSame([422, 'duplicate_field_key'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testAShowIfMayOnlyReferenceAnEarlierFieldThatExists(): void
    {
        $this->logInAs('administrator');
        $service = $this->service();

        $nonexistent = $this->request('POST', '/fields', [
            'scope' => 'service',
            'service_id' => $service,
            'field_key' => 'a',
            'type' => 'text',
            'label' => 'A',
            'sort' => 1,
            'show_if' => ['field' => 'ghost', 'equals' => '1'],
        ]);
        self::assertSame([422, 'invalid_show_if'], [$nonexistent['status'], $nonexistent['body']['code'] ?? null]);

        $self = $this->request('POST', '/fields', [
            'scope' => 'service',
            'service_id' => $service,
            'field_key' => 'b',
            'type' => 'text',
            'label' => 'B',
            'sort' => 1,
            'show_if' => ['field' => 'b', 'equals' => '1'],
        ]);
        self::assertSame([422, 'invalid_show_if'], [$self['status'], $self['body']['code'] ?? null]);

        $this->request('POST', '/fields', [
            'scope' => 'service',
            'service_id' => $service,
            'field_key' => 'has_car',
            'type' => 'checkbox',
            'label' => 'Has a car',
            'sort' => 1,
        ]);
        $later = $this->request('POST', '/fields', [
            'scope' => 'service',
            'service_id' => $service,
            'field_key' => 'plate',
            'type' => 'text',
            'label' => 'Plate number',
            'sort' => 1,
            'show_if' => ['field' => 'has_car', 'equals' => '1'],
        ]);
        self::assertSame(
            [422, 'invalid_show_if'],
            [$later['status'], $later['body']['code'] ?? null],
            'sort ties are not a guaranteed order, so a same-sort reference is rejected too'
        );

        $earlier = $this->request('POST', '/fields', [
            'scope' => 'service',
            'service_id' => $service,
            'field_key' => 'plate2',
            'type' => 'text',
            'label' => 'Plate number',
            'sort' => 2,
            'show_if' => ['field' => 'has_car', 'equals' => '1'],
        ]);
        self::assertSame([201, 'plate2'], [$earlier['status'], $earlier['body']['field_key'] ?? null]);
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
            'user_login' => 'field_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'field_' . $role . \count($this->users) . '@example.com',
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
