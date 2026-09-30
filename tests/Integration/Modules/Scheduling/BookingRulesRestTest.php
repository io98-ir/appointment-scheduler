<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Scheduling;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Options;

/**
 * The site-wide booking rules through the real REST server: who may change
 * them, what is stored, and what is refused.
 */
final class BookingRulesRestTest extends TestCase
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
        \delete_option(Options::key('settings_availability'));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function methods(): iterable
    {
        yield 'read' => ['GET'];
        yield 'save' => ['PUT'];
    }

    /**
     * @dataProvider methods
     */
    public function testAUserWithoutTheCapabilityIsForbidden(string $method): void
    {
        $this->logInAs('editor');

        $response = $this->request($method, [
            'slot_step_min' => 15,
            'min_notice_min' => 0,
            'max_advance_days' => 30,
        ]);

        self::assertSame([403, 'rest_forbidden'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testTheDefaultsAreReadUntilSavedThenKept(): void
    {
        $this->logInAs('administrator');
        self::assertSame(
            ['slot_step_min' => 30, 'min_notice_min' => 60, 'max_advance_days' => 60, 'staff_choice' => 'least_busy'],
            $this->request('GET')['body']
        );

        $saved = $this->request('PUT', [
            'slot_step_min' => 15,
            'min_notice_min' => 0,
            'max_advance_days' => 90,
            'staff_choice' => 'priority',
        ]);

        $expected = [
            'slot_step_min' => 15,
            'min_notice_min' => 0,
            'max_advance_days' => 90,
            'staff_choice' => 'priority',
        ];
        self::assertSame([200, $expected], [$saved['status'], $saved['body']]);
        self::assertSame($expected, $this->request('GET')['body']);
    }

    public function testAValueOutOfRangeIsRefusedAndChangesNothing(): void
    {
        $this->logInAs('administrator');
        $before = $this->request('GET')['body'];

        $bad = $this->request('PUT', ['slot_step_min' => 0, 'min_notice_min' => 60, 'max_advance_days' => 60]);

        self::assertSame([422, 'invalid_slot_step'], [$bad['status'], $bad['body']['code'] ?? null]);
        self::assertSame($before, $this->request('GET')['body']);
    }

    private function logInAs(string $role): void
    {
        $id = \wp_insert_user([
            'user_login' => 'rules_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'rules_' . $role . \count($this->users) . '@example.com',
            'role' => $role,
        ]);
        self::assertIsInt($id);
        $this->users[] = $id;
        \wp_set_current_user($id);
    }

    /**
     * @param array<string, mixed> $params A JSON body, or nothing for GET.
     * @return array{status: int, body: array<mixed>}
     */
    private function request(string $method, array $params = []): array
    {
        $request = new \WP_REST_Request($method, '/' . Identity::REST_NAMESPACE . '/booking-rules');
        if ('GET' !== $method) {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body((string) \wp_json_encode($params));
        }
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
    }
}
