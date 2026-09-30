<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Admin;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Options;

/**
 * The white-label, onboarding and gateway-settings APIs through the real REST
 * server (T6.1): who may call them, what is stored, and that a merchant id is
 * never sent back.
 */
final class SetupRestTest extends TestCase
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
        $options = [
            'settings_brand',
            'settings_general',
            'settings_onboarding',
            'settings_woocommerce_gateway',
            'secret_zarinpal_merchant',
        ];
        foreach ($options as $name) {
            \delete_option(Options::key($name));
        }
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    public function testTheModuleGaveAdministratorsThePaymentsCapability(): void
    {
        self::assertTrue(\get_role('administrator')?->has_cap(Caps::name('manage_payments')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routes(): iterable
    {
        yield 'read brand' => ['GET', '/brand'];
        yield 'save brand' => ['PUT', '/brand'];
        yield 'read display settings' => ['GET', '/general'];
        yield 'save display settings' => ['PUT', '/general'];
        yield 'read onboarding' => ['GET', '/onboarding'];
        yield 'save onboarding' => ['PUT', '/onboarding'];
        yield 'read payment settings' => ['GET', '/payments/settings'];
        yield 'save payment settings' => ['PUT', '/payments/settings'];
    }

    /**
     * @dataProvider routes
     */
    public function testAUserWithoutTheCapabilityIsForbidden(string $method, string $path): void
    {
        $this->logInAs('editor');

        $response = $this->request($method, $path, ['done' => true]);

        self::assertSame([403, 'rest_forbidden'], [$response['status'], $response['body']['code'] ?? null]);
    }

    public function testTheBrandIsEmptyUntilSetThenKeptAndValidated(): void
    {
        $this->logInAs('administrator');
        self::assertSame(
            ['name' => '', 'logo_url' => '', 'color' => ''],
            $this->request('GET', '/brand')['body']
        );

        $saved = $this->request('PUT', '/brand', [
            'name' => ' Salon Nima ',
            'logo_url' => 'https://example.test/logo.png',
            'color' => '#3858E9',
        ]);
        $expected = ['name' => 'Salon Nima', 'logo_url' => 'https://example.test/logo.png', 'color' => '#3858e9'];
        self::assertSame([200, $expected], [$saved['status'], $saved['body']]);
        self::assertSame($expected, $this->request('GET', '/brand')['body']);

        $bad = $this->request('PUT', '/brand', ['name' => 'X', 'color' => 'red']);
        self::assertSame([422, 'invalid_brand_color'], [$bad['status'], $bad['body']['code'] ?? null]);
        self::assertSame($expected, $this->request('GET', '/brand')['body'], 'A rejected save changes nothing.');
    }

    public function testTheDisplaySettingsDefaultToJalaliAndPersianDigitsThenAreKept(): void
    {
        $this->logInAs('administrator');
        $default = ['calendar' => 'jalali', 'digits' => 'persian', 'language' => 'auto'];
        self::assertSame($default, $this->request('GET', '/general')['body']);

        $saved = $this->request(
            'PUT',
            '/general',
            ['calendar' => 'gregorian', 'digits' => 'latin', 'language' => 'en']
        );

        $expected = ['calendar' => 'gregorian', 'digits' => 'latin', 'language' => 'en'];
        self::assertSame([200, $expected], [$saved['status'], $saved['body']]);
        self::assertSame($expected, $this->request('GET', '/general')['body']);

        $bad = $this->request('PUT', '/general', ['calendar' => 'lunar']);
        self::assertSame(400, $bad['status'], 'A value outside the enum is refused before it is stored.');
        self::assertSame($expected, $this->request('GET', '/general')['body']);
    }

    public function testTheWizardIsUndoneUntilFinishedAndCanBeReopened(): void
    {
        $this->logInAs('administrator');
        self::assertSame(['done' => false], $this->request('GET', '/onboarding')['body']);

        self::assertSame(['done' => true], $this->request('PUT', '/onboarding', ['done' => true])['body']);
        self::assertSame(['done' => true], $this->request('GET', '/onboarding')['body']);

        $this->request('PUT', '/onboarding', ['done' => false]);
        self::assertSame(['done' => false], $this->request('GET', '/onboarding')['body']);
    }

    public function testAMerchantIdIsStoredEncryptedAndNeverReturned(): void
    {
        $this->logInAs('administrator');
        self::assertSame(
            ['id' => 'zarinpal', 'secret' => 'zarinpal_merchant', 'set' => false, 'fixed' => false],
            self::firstGateway($this->request('GET', '/payments/settings')['body'])
        );

        $saved = $this->request('PUT', '/payments/settings', [
            'secrets' => ['zarinpal_merchant' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'],
        ]);

        self::assertSame(200, $saved['status']);
        self::assertTrue(self::firstGateway($saved['body'])['set'] ?? null);
        self::assertStringNotContainsString('aaaaaaaa', (string) \wp_json_encode($saved['body']));
        $stored = \get_option(Options::key('secret_zarinpal_merchant'));
        self::assertIsString($stored);
        self::assertStringNotContainsString('aaaaaaaa', $stored);

        $removed = $this->request('PUT', '/payments/settings', ['secrets' => ['zarinpal_merchant' => '']]);
        self::assertFalse(self::firstGateway($removed['body'])['set'] ?? null);
    }

    /**
     * @param array<mixed> $body A /payments/settings response.
     * @return array<mixed>
     */
    private static function firstGateway(array $body): array
    {
        $gateways = $body['gateways'] ?? null;
        $first = \is_array($gateways) ? ($gateways[0] ?? null) : null;

        return \is_array($first) ? $first : [];
    }

    public function testASecretNoGatewayOwnsOrABadMerchantIdIs422(): void
    {
        $this->logInAs('administrator');

        $unknown = $this->request('PUT', '/payments/settings', ['secrets' => ['sms_kavenegar_key' => 'x']]);
        $bad = $this->request('PUT', '/payments/settings', ['secrets' => ['zibal_merchant' => 'has space']]);

        self::assertSame([422, 'unknown_secret'], [$unknown['status'], $unknown['body']['code'] ?? null]);
        self::assertSame([422, 'invalid_merchant'], [$bad['status'], $bad['body']['code'] ?? null]);
    }

    private function logInAs(string $role): void
    {
        $id = \wp_insert_user([
            'user_login' => 'setup_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'setup_' . $role . \count($this->users) . '@example.com',
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
