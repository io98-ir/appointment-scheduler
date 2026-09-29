<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Admin\Application\SetupService;
use Vaqtyar\Modules\Admin\Application\SetupStore;
use Vaqtyar\Modules\Admin\Infrastructure\SettingsSetupStore;
use Vaqtyar\Modules\Admin\Presentation\AdminPage;
use Vaqtyar\Modules\Admin\Presentation\Rest\SetupRoutes;
use Vaqtyar\Shared\WpAuthorizer;

/**
 * wp-admin: the page the admin app runs in, and the white-label look and
 * onboarding state it reads (T6.1). Dashboard, reports and settings screens
 * are the app's; the data behind them belongs to the other modules
 * (architecture §2).
 */
final class AdminModule implements Module
{
    public function id(): string
    {
        return 'admin';
    }

    public function register(Container $container): void
    {
        $container->singleton(
            SetupStore::class,
            static fn (Container $c) => new SettingsSetupStore($c->get(Settings::class))
        );
        $container->singleton(
            SetupService::class,
            static fn (Container $c) => new SetupService(new WpAuthorizer(), $c->get(SetupStore::class))
        );
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
        return [SetupService::CAPABILITY => ['administrator']];
    }

    public function boot(Context $context): void
    {
        $container = $context->container;
        \add_action('rest_api_init', static function () use ($container): void {
            (new SetupRoutes($container->get(Router::class), $container->get(SetupService::class)))->register();
        });

        if (!\is_admin()) {
            return;
        }
        $page = new AdminPage($context->pluginFile, $container->get(Settings::class));
        \add_action('admin_menu', [$page, 'register']);
        \add_action('admin_enqueue_scripts', [$page, 'enqueue']);
    }
}
