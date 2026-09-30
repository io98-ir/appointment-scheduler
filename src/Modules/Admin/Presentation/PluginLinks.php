<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Presentation;

use Vaqtyar\Kernel\Identity;

/**
 * The plugin's row in the plugins list: a link to its settings, and a line
 * crediting the maker, like every plugin of theirs carries.
 */
final class PluginLinks
{
    public function __construct(private readonly string $pluginFile)
    {
    }

    public function register(): void
    {
        \add_filter('plugin_action_links_' . \plugin_basename($this->pluginFile), [$this, 'actionLinks']);
        \add_filter('plugin_row_meta', [$this, 'rowMeta'], 10, 2);
    }

    /**
     * @param array<array-key, string> $links
     * @return array<array-key, string>
     */
    public function actionLinks(array $links): array
    {
        $settings = \sprintf(
            '<a href="%s">%s</a>',
            \esc_url(\admin_url('admin.php?page=' . Identity::SLUG . '#/settings')),
            \esc_html__('Settings', 'vaqtyar')
        );

        return \array_merge([$settings], $links);
    }

    /**
     * @param array<array-key, string> $meta
     * @return array<array-key, string>
     */
    public function rowMeta(array $meta, string $file): array
    {
        if (\plugin_basename($this->pluginFile) !== $file) {
            return $meta;
        }
        $meta[] = \sprintf(
            '<a href="%s" target="_blank" rel="noopener">%s</a>',
            \esc_url(Identity::AUTHOR_URL),
            /* translators: %s: the maker's name, io98 */
            \esc_html(\sprintf(\__('Made by %s', 'vaqtyar'), Identity::AUTHOR))
        );

        return $meta;
    }
}
