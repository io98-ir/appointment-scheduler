<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Database\Migrator;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Log\CreateLogsTable;
use Vaqtyar\Kernel\Log\Logger;
use Vaqtyar\Kernel\Rest\CreateRateLimitsTable;
use Vaqtyar\Kernel\Rest\RateLimiter;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\Settings\GeneralSettings;
use Vaqtyar\Kernel\Settings\ModuleSettings;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Shared\DateFormatter;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Shared\Domain\Jalali;
use Vaqtyar\Shared\SystemClock;

/**
 * Boots the plugin on plugins_loaded. The main plugin file is the composition
 * root: it passes the module instances, so the kernel depends on no module.
 */
final class Plugin
{
    /** The owner of the kernel's own tables in the migration versions; no module may use it. */
    public const KERNEL_ID = 'kernel';

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
        $catalog = self::catalog($modules);
        $registry = new ModuleRegistry(...$catalog->active());
        $container = new Container();
        $container->singleton(ModuleCatalog::class, static fn (): ModuleCatalog => $catalog);
        $container->singleton(Db::class, static fn (): Db => Db::fromGlobals());
        $container->singleton(
            Transaction::class,
            static fn (Container $c): Transaction => new Transaction($c->get(Db::class))
        );
        $container->singleton(
            TransactionRunner::class,
            static fn (Container $c): TransactionRunner => $c->get(Transaction::class)
        );
        $container->singleton(Clock::class, static fn (): Clock => new SystemClock());
        $container->singleton(RequestId::class, static fn (): RequestId => new RequestId());
        $container->singleton(
            RateLimiter::class,
            static fn (Container $c): RateLimiter => new RateLimiter($c->get(Db::class), $c->get(Clock::class))
        );
        $container->singleton(
            Logger::class,
            static fn (Container $c): Logger => new Logger(
                $c->get(Db::class),
                $c->get(Clock::class),
                $c->get(RequestId::class)
            )
        );
        $container->singleton(
            Router::class,
            static fn (Container $c): Router => new Router(
                $c->get(RateLimiter::class),
                $c->get(RequestId::class),
                $c->get(Logger::class)
            )
        );
        $container->singleton(Settings::class, static fn (): Settings => new Settings());
        $pluginFile = $this->pluginFile;
        $container->singleton(
            Localization::class,
            static fn (Container $c): Localization => new Localization($pluginFile, $c->get(Settings::class))
        );
        $container->singleton(
            SecretStore::class,
            static fn (Container $c): SecretStore => new SecretStore(
                SecretStore::keyFromSalt(\wp_salt('auth')),
                $c->get(Logger::class)
            )
        );
        $container->singleton(DateFormatter::class, static function (Container $c): DateFormatter {
            $general = $c->get(Settings::class)->get(GeneralSettings::class);

            return new DateFormatter(new Jalali(), $general->calendar, $general->digits);
        });

        foreach ($registry->all() as $module) {
            $module->register($container);
        }

        // After an update the activation hook does not run, so the schema
        // catches up here. When another request holds the migration lock,
        // this one goes on with the schema it finds. With the schema current
        // this reads one autoloaded option and sends no query.
        try {
            (new Migrator($container->get(Db::class)))->migrate($this->migrations($registry));
        } catch (\Throwable $e) {
            // The host refused a schema change (no ALTER privilege, no InnoDB, …):
            // a condition of the server, not a bug. Throwing here would break
            // every page of the site, so the plugin stays off until it is fixed.
            $this->reportFailedMigration($e);

            return;
        }

        // Like the migrations: activation does not run after an update.
        (new Capabilities())->grant($this->capabilities($registry));

        $container->get(Localization::class)->register();

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
        $registry = new ModuleRegistry(...self::catalog($modules)->active());
        (new Migrator(Db::fromGlobals()))->migrate($this->migrations($registry));
        (new Capabilities())->grant($this->capabilities($registry));
    }

    /**
     * @param array<int|string, Module> $modules
     */
    private static function catalog(array $modules): ModuleCatalog
    {
        return new ModuleCatalog($modules, (new Settings())->get(ModuleSettings::class)->disabled);
    }

    private function reportFailedMigration(\Throwable $e): void
    {
        // Not the Logger: the database is what failed (the logs table may be
        // the missing piece), and the notice below sends the admin to a log
        // they can read without the plugin. Without the server's error text,
        // which may quote data (DbException).
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- see above.
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
     * The kernel's own tables first: modules may rely on them.
     *
     * @return array<string, list<Migration>>
     */
    private function migrations(ModuleRegistry $registry): array
    {
        // Append only, like a module's list (Module::migrations()).
        $migrations = [self::KERNEL_ID => [new CreateRateLimitsTable(), new CreateLogsTable()]];
        foreach ($registry->all() as $module) {
            $list = $module->migrations();
            if ([] !== $list) {
                $migrations[$module->id()] = $list;
            }
        }

        return $migrations;
    }

    /**
     * Two modules may name the same capability; the roles add up.
     *
     * @return array<string, list<string>>
     */
    private function capabilities(ModuleRegistry $registry): array
    {
        $capabilities = [];
        foreach ($registry->all() as $module) {
            foreach ($module->capabilities() as $capability => $roles) {
                $capabilities[$capability] = \array_values(\array_unique(
                    \array_merge($capabilities[$capability] ?? [], $roles)
                ));
            }
        }

        return $capabilities;
    }
}
