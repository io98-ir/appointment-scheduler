<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Presentation;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Settings\BrandSettings;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Admin\Application\SetupService;
use Vaqtyar\Shared\Domain\Brand;

/**
 * The plugin's page in wp-admin: one element that the admin app
 * (packages/admin) mounts into, and the app's script and style, loaded on
 * this page only (implementation-notes §7).
 */
final class AdminPage
{
    /** Opens the plugin's admin app (short name, Caps::name()). */
    public const CAPABILITY = SetupService::CAPABILITY;

    private const ENTRY = 'admin';

    private string $hookSuffix = '';

    /**
     * @param string $pluginFile The main plugin file; the build is in build/ next to it.
     */
    public function __construct(private readonly string $pluginFile, private readonly Settings $settings)
    {
    }

    /**
     * On admin_menu.
     */
    public function register(): void
    {
        $name = $this->brand()->displayName(Identity::NAME);
        $this->hookSuffix = \add_menu_page(
            $name,
            $name,
            Caps::name(self::CAPABILITY),
            Identity::SLUG,
            [$this, 'render'],
            'dashicons-calendar-alt',
            26
        );
    }

    /**
     * On admin_enqueue_scripts.
     */
    public function enqueue(string $hookSuffix): void
    {
        if ('' === $this->hookSuffix || $hookSuffix !== $this->hookSuffix) {
            return;
        }
        $asset = $this->asset();
        if (null === $asset) {
            return;
        }

        // The media library, for choosing the logo.
        \wp_enqueue_media();
        $handle = self::handle();
        \wp_enqueue_script(
            $handle,
            \plugins_url('build/' . self::ENTRY . '.js', $this->pluginFile),
            $asset['dependencies'],
            $asset['version'],
            ['in_footer' => true]
        );
        \wp_set_script_translations($handle, 'vaqtyar', \dirname($this->pluginFile) . '/languages');
        // Logical properties only, so one stylesheet serves RTL and LTR.
        // wp-components: the script loads its JS, not its stylesheet.
        \wp_enqueue_style(
            $handle,
            \plugins_url('build/' . self::ENTRY . '.css', $this->pluginFile),
            ['wp-components'],
            $asset['version']
        );
    }

    /**
     * The page callback of add_menu_page().
     */
    public function render(): void
    {
        if (null === $this->asset()) {
            // Only a development checkout without `pnpm build`; a release zip has the build.
            \printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                \esc_html__(
                    'The admin app is not built. Run "pnpm install" and "pnpm build" in the plugin folder.',
                    'vaqtyar'
                )
            );

            return;
        }

        // What packages/admin/src/config.ts reads: JSON in an attribute, no global.
        $config = [
            'restUrl' => \rest_url(Identity::REST_NAMESPACE . '/'),
            'nonce' => \wp_create_nonce('wp_rest'),
            'brand' => [
                'name' => $this->brand()->name,
                'logo_url' => $this->brand()->logoUrl,
                'color' => $this->brand()->color,
            ],
            'product_name' => Identity::NAME,
        ];
        \printf(
            '<div class="wrap"><div id="%s" data-config="%s"></div></div>',
            \esc_attr(Identity::SLUG . '-admin'),
            \esc_attr((string) \wp_json_encode($config))
        );
    }

    private function brand(): Brand
    {
        return $this->settings->get(BrandSettings::class)->brand;
    }

    /**
     * @return non-empty-string
     */
    public static function handle(): string
    {
        return Identity::SLUG . '-' . self::ENTRY;
    }

    /**
     * The dependencies and version wp-scripts wrote next to the script.
     *
     * @return array{dependencies: list<non-empty-string>, version: string}|null Null when not built.
     */
    private function asset(): ?array
    {
        $file = \dirname($this->pluginFile) . '/build/' . self::ENTRY . '.asset.php';
        if (!\is_file($file)) {
            return null;
        }
        $asset = require $file;
        if (
            !\is_array($asset)
            || !\is_string($asset['version'] ?? null)
            || !\is_array($asset['dependencies'] ?? null)
        ) {
            return null;
        }

        $dependencies = [];
        foreach ($asset['dependencies'] as $dependency) {
            if (\is_string($dependency) && '' !== $dependency) {
                $dependencies[] = $dependency;
            }
        }

        return ['dependencies' => $dependencies, 'version' => $asset['version']];
    }
}
