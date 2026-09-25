<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Database\Migrator;
use Vaqtyar\Kernel\Database\Transaction;

/**
 * Boots the plugin on plugins_loaded. The main plugin file is the composition
 * root: it passes the module instances, so the kernel depends on no module.
 */
final class Plugin
{
    public function __construct(
        private readonly string $pluginFile,
        private readonly string $version,
    ) {
    }

    /**
     * A module that throws here is a bug and is left to fail loudly: WordPress
     * recovery mode pauses the plugin and tells the site owner. A failed
     * migration is not: see below.
     */
    public function boot(Module ...$modules): void
    {
        $registry = new ModuleRegistry(...$modules);
        $container = new Container();
        $container->singleton(Db::class, static fn (): Db => Db::fromGlobals());
        $container->singleton(
            Transaction::class,
            static fn (Container $c): Transaction => new Transaction($c->get(Db::class))
        );

        foreach ($registry->all() as $module) {
            $module->register($container);
        }

        // After an update the activation hook does not run, so the schema
        // catches up here. When another request holds the migration lock,
        // this one goes on with the schema it finds.
        $migrations = $this->migrations($registry);
        if ([] !== $migrations) {
            try {
                (new Migrator($container->get(Db::class)))->migrate($migrations);
            } catch (\Throwable $e) {
                // The host refused a schema change (no ALTER privilege, no InnoDB, …):
                // a condition of the server, not a bug. Throwing here would break
                // every page of the site, so the plugin stays off until it is fixed.
                $this->reportFailedMigration($e);

                return;
            }
        }

        $context = new Context($container, $this->pluginFile, $this->version);
        foreach ($registry->all() as $module) {
            $module->boot($context);
        }
    }

    /**
     * Runs from the activation hook in the main file. That request has passed
     * plugins_loaded before the plugin was included, so boot() never runs in
     * it (implementation-notes §1). On a network activation only the current
     * site migrates here; every other site does on its first request.
     */
    public function activate(Module ...$modules): void
    {
        $migrations = $this->migrations(new ModuleRegistry(...$modules));
        if ([] !== $migrations) {
            (new Migrator(Db::fromGlobals()))->migrate($migrations);
        }
    }

    private function reportFailedMigration(\Throwable $e): void
    {
        // Without the server's error text, which may quote data (DbException).
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- no Logger before migrations.
        \error_log(\sprintf(
            '%s: a database migration failed, the plugin is paused: %s %s',
            Identity::NAME,
            $e::class,
            $e->getMessage()
        ));

        \add_action('admin_notices', static function (): void {
            if (!\current_user_can('activate_plugins')) {
                return;
            }
            \printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                \esc_html(\sprintf(
                    /* translators: %s: plugin name */
                    \__(
                        '%s could not update its database tables and is paused. The PHP error log has the details.',
                        'vaqtyar'
                    ),
                    Identity::NAME
                ))
            );
        });
    }

    /**
     * @return array<string, list<Migration>>
     */
    private function migrations(ModuleRegistry $registry): array
    {
        $migrations = [];
        foreach ($registry->all() as $module) {
            $list = $module->migrations();
            if ([] !== $list) {
                $migrations[$module->id()] = $list;
            }
        }

        return $migrations;
    }
}
