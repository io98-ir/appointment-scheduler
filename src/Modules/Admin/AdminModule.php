<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Modules\Admin\Presentation\AdminPage;

/**
 * wp-admin: the page the admin app runs in. Dashboard, reports, settings and
 * onboarding join it in M3 and M6 (architecture §2).
 */
final class AdminModule implements Module
{
    public function id(): string
    {
        return 'admin';
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
        return [AdminPage::CAPABILITY => ['administrator']];
    }

    public function boot(Context $context): void
    {
        if (!\is_admin()) {
            return;
        }
        $page = new AdminPage($context->pluginFile);
        \add_action('admin_menu', [$page, 'register']);
        \add_action('admin_enqueue_scripts', [$page, 'enqueue']);
    }
}
