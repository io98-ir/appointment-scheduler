<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Widget;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Settings\BrandSettings;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Shared\Domain\Brand;

/**
 * The shortcodes and blocks that put the widget and the panel on a page
 * (T4.5): an element with its config, and the script only where one renders.
 * Without `pnpm build` a guest gets nothing, so both cases are checked.
 */
final class EmbedsTest extends TestCase
{
    private function built(): bool
    {
        return \is_file(\dirname(\VAQTYAR_FILE) . '/build/widget.asset.php');
    }

    /**
     * @return array<string, mixed>
     */
    private static function configOf(string $html, string $kind): array
    {
        $found = \preg_match('/data-' . \preg_quote(Identity::SLUG, '/') . '-' . $kind . '="([^"]*)"/', $html, $m);
        self::assertSame(1, $found, $html);
        $config = \json_decode(\html_entity_decode($m[1]), true);
        self::assertIsArray($config);

        return $config;
    }

    public function testTheShortcodesAndBlocksAreRegistered(): void
    {
        self::assertTrue(\shortcode_exists(Identity::SLUG . '_booking'));
        self::assertTrue(\shortcode_exists(Identity::SLUG . '_panel'));
        self::assertTrue(\WP_Block_Type_Registry::get_instance()->is_registered(Identity::SLUG . '/booking'));
        self::assertTrue(\WP_Block_Type_Registry::get_instance()->is_registered(Identity::SLUG . '/panel'));
    }

    public function testAnEmbedIsAnElementWithItsConfigAndTheScriptLoadsOnlyThen(): void
    {
        $handle = Identity::SLUG . '-widget';
        self::assertFalse(\wp_script_is($handle, 'enqueued'));

        $html = \do_shortcode(
            '[' . Identity::SLUG . '_booking service="7" staff="x" calendar="gregorian" digits="bogus"]'
        );

        if (!$this->built()) {
            self::assertSame('', $html, 'A guest sees nothing where the build is missing.');
            self::assertFalse(\wp_script_is($handle, 'enqueued'));

            return;
        }
        $config = self::configOf($html, 'widget');
        self::assertSame(7, $config['service'] ?? null);
        self::assertArrayNotHasKey('staff', $config);
        self::assertArrayNotHasKey('variant', $config);
        self::assertSame(['gregorian', 'latin'], [$config['calendar'] ?? null, $config['digits'] ?? null]);
        $restUrl = $config['restUrl'] ?? null;
        self::assertStringContainsString(Identity::REST_NAMESPACE, \is_string($restUrl) ? $restUrl : '');
        self::assertTrue(\wp_script_is($handle, 'enqueued'));
        self::assertTrue(\wp_style_is($handle, 'enqueued'));
    }

    public function testTheOwnersColourIsAStyleOnTheElementAndNothingElseIsAdded(): void
    {
        if (!$this->built()) {
            self::markTestSkipped('The widget is not built.');
        }
        $settings = new Settings();
        $none = \do_shortcode('[' . Identity::SLUG . '_booking]');
        self::assertStringNotContainsString('-accent', $none);

        $settings->save(new BrandSettings(Brand::of('', '', '#112233')));
        try {
            $html = \do_shortcode('[' . Identity::SLUG . '_booking]');
        } finally {
            $settings->save(new BrandSettings());
        }

        self::assertStringContainsString(' style="--' . Identity::PREFIX . '-accent:#112233"', $html);
    }

    public function testThePanelHasItsOwnElement(): void
    {
        $html = \do_shortcode('[' . Identity::SLUG . '_panel]');

        if (!$this->built()) {
            self::assertSame('', $html);

            return;
        }
        $config = self::configOf($html, 'panel');
        self::assertSame(['jalali', 'latin'], [$config['calendar'] ?? null, $config['digits'] ?? null]);
    }

    public function testABlockRendersLikeItsShortcode(): void
    {
        $html = \render_block([
            'blockName' => Identity::SLUG . '/booking',
            'attrs' => ['service' => 3, 'location' => 1],
            'innerBlocks' => [],
            'innerHTML' => '',
            'innerContent' => [],
        ]);

        if (!$this->built()) {
            self::assertSame('', $html);

            return;
        }
        $config = self::configOf($html, 'widget');
        self::assertSame([3, 1], [$config['service'] ?? null, $config['location'] ?? null]);
    }
}
