<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Admin;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Modules\Admin\Presentation\AdminPage;

/**
 * The admin page with a stand-in build (tests/Integration/Fixtures/built-plugin),
 * so the suite needs no `pnpm build`.
 */
final class AdminPageTest extends \WP_UnitTestCase
{
    private const BUILT = __DIR__ . '/../../Fixtures/built-plugin/plugin.php';

    public function set_up(): void
    {
        parent::set_up();
        // add_menu_page() and get_plugin_page_hookname(); a test request is not an admin one.
        require_once \ABSPATH . 'wp-admin/includes/plugin.php';
    }

    public function tear_down(): void
    {
        \wp_dequeue_script(AdminPage::handle());
        \wp_deregister_script(AdminPage::handle());
        \wp_dequeue_style(AdminPage::handle());
        \wp_deregister_style(AdminPage::handle());
        parent::tear_down();
    }

    public function testAnAdministratorHasTheCapabilityTheMenuNeeds(): void
    {
        // Given on the test site's boot (Capabilities), like on a real one.
        $admin = self::userWithRole('administrator');
        $editor = self::userWithRole('editor');

        self::assertTrue(\user_can($admin, Caps::name(AdminPage::CAPABILITY)));
        self::assertFalse(\user_can($editor, Caps::name(AdminPage::CAPABILITY)));
    }

    public function testRendersTheElementTheAppMountsInto(): void
    {
        $page = new AdminPage(self::BUILT);

        \ob_start();
        $page->render();
        $html = (string) \ob_get_clean();

        self::assertMatchesRegularExpression(
            '/<div id="' . Identity::SLUG . '-admin" data-config="[^"]*"><\/div>/',
            $html
        );
    }

    public function testHandsTheAppTheRestBaseAndANonce(): void
    {
        \wp_set_current_user(self::userWithRole('administrator'));
        $page = new AdminPage(self::BUILT);

        \ob_start();
        $page->render();
        $html = (string) \ob_get_clean();

        self::assertSame(1, \preg_match('/data-config="([^"]*)"/', $html, $match));
        $config = \json_decode(\html_entity_decode($match[1] ?? '', \ENT_QUOTES), true);
        self::assertIsArray($config);
        self::assertSame(\rest_url(Identity::REST_NAMESPACE . '/'), $config['restUrl'] ?? null);
        $nonce = $config['nonce'] ?? null;
        self::assertIsString($nonce);
        self::assertSame(1, \wp_verify_nonce($nonce, 'wp_rest'));
    }

    public function testLoadsTheBuildOnItsOwnPageOnly(): void
    {
        \wp_set_current_user(self::userWithRole('administrator'));
        $page = new AdminPage(self::BUILT);
        $page->register();
        $hookSuffix = \get_plugin_page_hookname(Identity::SLUG, '');

        $page->enqueue('index.php');
        self::assertFalse(\wp_script_is(AdminPage::handle(), 'enqueued'));

        $page->enqueue($hookSuffix);
        self::assertTrue(\wp_script_is(AdminPage::handle(), 'enqueued'));
        self::assertTrue(\wp_style_is(AdminPage::handle(), 'enqueued'));
        $script = \wp_scripts()->registered[AdminPage::handle()] ?? null;
        self::assertInstanceOf(\_WP_Dependency::class, $script);
        self::assertSame(['react-jsx-runtime', 'wp-element', 'wp-i18n'], $script->deps);
        self::assertSame('abc123', $script->ver);
        $style = \wp_styles()->registered[AdminPage::handle()] ?? null;
        self::assertInstanceOf(\_WP_Dependency::class, $style);
        self::assertSame(['wp-components'], $style->deps);
    }

    public function testWithoutABuildItSaysSoInsteadOfABlankPage(): void
    {
        \wp_set_current_user(self::userWithRole('administrator'));
        $page = new AdminPage(__DIR__ . '/no-build/plugin.php');
        $page->register();

        \ob_start();
        $page->render();
        $html = (string) \ob_get_clean();
        $page->enqueue(\get_plugin_page_hookname(Identity::SLUG, ''));

        self::assertStringContainsString('pnpm build', $html);
        self::assertFalse(\wp_script_is(AdminPage::handle(), 'registered'));
    }

    private static function userWithRole(string $role): int
    {
        $user = self::factory()->user->create_and_get(['role' => $role]);
        self::assertInstanceOf(\WP_User::class, $user);

        return $user->ID;
    }
}
