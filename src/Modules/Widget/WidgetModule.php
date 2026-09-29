<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Widget;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Widget\Presentation\Embeds;

/**
 * The booking widget and the customer panel on the front end (T4.5): a
 * shortcode and a block for each, loading their assets only where used. The
 * widget itself is packages/widget; its data comes from the REST API of the
 * other modules, so this module holds no data.
 */
final class WidgetModule implements Module
{
    public function id(): string
    {
        return 'widget';
    }

    public function register(Container $container): void
    {
    }

    /**
     * @return list<\Vaqtyar\Kernel\Database\Migration>
     */
    public function migrations(): array
    {
        return [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function capabilities(): array
    {
        return [];
    }

    public function boot(Context $context): void
    {
        $embeds = new Embeds($context->pluginFile, $context->container->get(Settings::class));
        \add_action('init', [$embeds, 'register']);
        \add_action('enqueue_block_editor_assets', [$embeds, 'enqueueEditor']);
    }
}
