<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Widget\Presentation;

use Vaqtyar\Kernel\Identity;

/**
 * Puts the booking widget and the customer panel on a page (T4.5), as a
 * shortcode or as a block: an element with its config as JSON in an
 * attribute, which packages/widget mounts. The widget's script and style are
 * enqueued when the first embed renders, so a page without one loads
 * nothing.
 */
final class Embeds
{
    private const ENTRY = 'widget';
    private const BOOKING = 'booking';
    private const PANEL = 'panel';

    private const CALENDARS = ['jalali', 'gregorian'];
    private const DIGITS = ['latin', 'persian'];

    private bool $enqueued = false;

    /**
     * @param string $pluginFile The main plugin file; the build is in build/ next to it.
     */
    public function __construct(private readonly string $pluginFile)
    {
    }

    /**
     * On init.
     */
    public function register(): void
    {
        \add_shortcode(Identity::SLUG . '_' . self::BOOKING, [$this, 'renderBooking']);
        \add_shortcode(Identity::SLUG . '_' . self::PANEL, [$this, 'renderPanel']);

        $ids = ['type' => 'integer', 'default' => 0];
        $look = [
            'calendar' => ['type' => 'string', 'default' => 'jalali'],
            'digits' => ['type' => 'string', 'default' => 'latin'],
        ];
        \register_block_type(Identity::SLUG . '/' . self::BOOKING, [
            'api_version' => '3',
            'title' => \__('Booking form', 'vaqtyar'),
            'description' => \__('Lets customers book an appointment.', 'vaqtyar'),
            'category' => 'widgets',
            'icon' => 'calendar-alt',
            'attributes' => [
                'service' => $ids,
                'variant' => $ids,
                'location' => $ids,
                'staff' => $ids,
            ] + $look,
            'render_callback' => [$this, 'renderBookingBlock'],
        ]);
        \register_block_type(Identity::SLUG . '/' . self::PANEL, [
            'api_version' => '3',
            'title' => \__('My appointments', 'vaqtyar'),
            'description' => \__('Lets a customer see, cancel and move their appointments.', 'vaqtyar'),
            'category' => 'widgets',
            'icon' => 'list-view',
            'attributes' => $look,
            'render_callback' => [$this, 'renderPanelBlock'],
        ]);
    }

    /**
     * On enqueue_block_editor_assets: the small script that gives both
     * blocks their editor. It reads the block names from its own tag, so
     * it holds no brand name and defines no global.
     */
    public function enqueueEditor(): void
    {
        $handle = Identity::SLUG . '-blocks';
        \wp_enqueue_script(
            $handle,
            \plugins_url('assets/blocks.js', $this->pluginFile),
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n'],
            (string) (\filemtime(\dirname($this->pluginFile) . '/assets/blocks.js') ?: '1'),
            ['in_footer' => true]
        );
        \wp_set_script_translations($handle, 'vaqtyar', \dirname($this->pluginFile) . '/languages');
        \add_filter('script_loader_tag', static function (string $tag, string $current) use ($handle): string {
            if ($current !== $handle) {
                return $tag;
            }
            $names = \wp_json_encode([Identity::SLUG . '/' . self::BOOKING, Identity::SLUG . '/' . self::PANEL]);

            return \str_replace('<script ', '<script data-blocks="' . \esc_attr((string) $names) . '" ', $tag);
        }, 10, 2);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderBooking(array|string $atts): string
    {
        $values = \shortcode_atts(
            [
                'service' => 0,
                'variant' => 0,
                'location' => 0,
                'staff' => 0,
                'calendar' => 'jalali',
                'digits' => 'latin',
            ],
            \is_array($atts) ? $atts : []
        );

        return $this->mount(self::BOOKING, [
            'service' => self::id($values['service']),
            'variant' => self::id($values['variant']),
            'location' => self::id($values['location']),
            'staff' => self::id($values['staff']),
        ] + self::look($values));
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderPanel(array|string $atts): string
    {
        $values = \shortcode_atts(
            ['calendar' => 'jalali', 'digits' => 'latin'],
            \is_array($atts) ? $atts : []
        );

        return $this->mount(self::PANEL, self::look($values));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderBookingBlock(array $attributes): string
    {
        return $this->renderBooking($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderPanelBlock(array $attributes): string
    {
        return $this->renderPanel($attributes);
    }

    /**
     * @param array<string, mixed> $values
     * @return array{calendar: string, digits: string}
     */
    private static function look(array $values): array
    {
        $calendar = $values['calendar'] ?? null;
        $digits = $values['digits'] ?? null;

        return [
            'calendar' => \is_string($calendar) && \in_array($calendar, self::CALENDARS, true) ? $calendar : 'jalali',
            'digits' => \is_string($digits) && \in_array($digits, self::DIGITS, true) ? $digits : 'latin',
        ];
    }

    private static function id(mixed $value): int
    {
        return \is_numeric($value) ? \max(0, (int) $value) : 0;
    }

    /**
     * @param array<string, int|string> $config
     */
    private function mount(string $kind, array $config): string
    {
        if (!$this->enqueue()) {
            // Only a development checkout without `pnpm build`; a release zip has the build.
            return \current_user_can('manage_options')
                ? '<p>' . \esc_html__('The booking widget is not built. Run "pnpm build".', 'vaqtyar') . '</p>'
                : '';
        }
        // Zeros mean "not set", which the widget reads as absent.
        $config = \array_filter($config, static fn (int|string $value): bool => 0 !== $value);
        $config['restUrl'] = \rest_url(Identity::REST_NAMESPACE . '/');
        // The query parameter Payments' return redirect carries the outcome in.
        $config['paymentParam'] = Identity::PREFIX . '_payment';

        return \sprintf(
            '<div data-%s-%s="%s"></div>',
            \esc_attr(Identity::SLUG),
            \esc_attr(self::BOOKING === $kind ? self::ENTRY : $kind),
            \esc_attr((string) \wp_json_encode($config))
        );
    }

    /**
     * Enqueues the widget's script and style, once.
     *
     * @return bool False when the build is missing.
     */
    private function enqueue(): bool
    {
        if ($this->enqueued) {
            return true;
        }
        $asset = $this->asset();
        if (null === $asset) {
            return false;
        }
        $handle = Identity::SLUG . '-' . self::ENTRY;
        \wp_enqueue_script(
            $handle,
            \plugins_url('build/' . self::ENTRY . '.js', $this->pluginFile),
            $asset['dependencies'],
            $asset['version'],
            ['in_footer' => true]
        );
        \wp_set_script_translations($handle, 'vaqtyar', \dirname($this->pluginFile) . '/languages');
        // Logical properties only, so one stylesheet serves RTL and LTR.
        \wp_enqueue_style(
            $handle,
            \plugins_url('build/' . self::ENTRY . '.css', $this->pluginFile),
            [],
            $asset['version']
        );
        $this->enqueued = true;

        return true;
    }

    /**
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
