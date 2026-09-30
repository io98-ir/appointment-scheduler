<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\ModuleCatalog;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Admin\Application\SetupService;
use Vaqtyar\Modules\Admin\Application\SetupStore;
use Vaqtyar\Modules\Admin\Application\StatusService;
use Vaqtyar\Modules\Admin\Application\StatusSource;
use Vaqtyar\Modules\Admin\Application\ModuleSwitches;
use Vaqtyar\Modules\Admin\Domain\HealthEvaluator;
use Vaqtyar\Modules\Admin\Infrastructure\SettingsModuleSwitches;
use Vaqtyar\Modules\Admin\Infrastructure\WpStatusSource;
use Vaqtyar\Modules\Admin\Presentation\Rest\StatusRoutes;
use Vaqtyar\Modules\Admin\Presentation\SiteHealthTests;
use Vaqtyar\Shared\Domain\Clock;
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
        $container->singleton(
            ModuleSwitches::class,
            static fn (Container $c) => new SettingsModuleSwitches(
                $c->get(ModuleCatalog::class),
                $c->get(Settings::class)
            )
        );
        $container->singleton(
            StatusService::class,
            static fn (Container $c) => new StatusService(
                new WpAuthorizer(),
                $c->get(StatusSource::class),
                $c->get(ModuleSwitches::class)
            )
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
        return [
            SetupService::CAPABILITY => ['administrator'],
            StatusService::CAPABILITY => ['administrator'],
        ];
    }

    public function boot(Context $context): void
    {
        $container = $context->container;
        $version = $context->version;
        // Here and not in register(): the plugin's version reaches a module through the Context.
        $container->singleton(
            StatusSource::class,
            static fn (Container $c) => new WpStatusSource($c->get(Db::class), $c->get(Clock::class), $version)
        );
        \add_action('rest_api_init', static function () use ($container): void {
            (new SetupRoutes($container->get(Router::class), $container->get(SetupService::class)))->register();
            (new StatusRoutes($container->get(Router::class), $container->get(StatusService::class)))->register();
        });
        \add_filter('site_status_tests', static fn (array $tests): array => (new SiteHealthTests(
            $container->get(StatusSource::class),
            new HealthEvaluator()
        ))->register($tests));

        if (!\is_admin()) {
            return;
        }
        $page = new AdminPage($context->pluginFile, $container->get(Settings::class));
        \add_action('admin_menu', [$page, 'register']);
        \add_action('admin_enqueue_scripts', [$page, 'enqueue']);
    }
}
