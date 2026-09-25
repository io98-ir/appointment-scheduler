<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel;

use ArrayObject;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Log\Logger;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Kernel\Plugin;
use Vaqtyar\Kernel\RequestId;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\SecretStore;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Shared\DateFormatter;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\SystemClock;
use Vaqtyar\Tests\Unit\Kernel\Database\FakesWpdb;
use Vaqtyar\Tests\Unit\Kernel\Fixtures\RecordingMigration;
use Vaqtyar\Tests\Unit\Kernel\Fixtures\SampleModule;

final class PluginTest extends TestCase
{
    use FakesWpdb;

    /** @var array<string, mixed> */
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('get_option')->alias(
            fn (string $name, mixed $default = false): mixed => $this->options[$name] ?? $default
        );
        Functions\when('update_option')->alias(function (string $name, mixed $value): bool {
            $this->options[$name] = $value;

            return true;
        });
        Functions\when('wp_cache_delete')->justReturn(true);
        $this->fakeWpdb();
        $this->respondWith(static fn (string $sql): string|bool => match (true) {
            \str_contains($sql, 'GET_LOCK') => '1',
            \str_contains($sql, 'ENGINE') => 'InnoDB',
            default => true,
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $this->tearDownWpdb();
        parent::tearDown();
    }

    public function testRegistersEveryModuleBeforeBootingAny(): void
    {
        /** @var ArrayObject<int, string> $log */
        $log = new ArrayObject();
        $catalog = new SampleModule('catalog', $log);
        $booking = new SampleModule('booking', $log, bindsService: true);

        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot($catalog, $booking);

        self::assertSame(
            ['catalog:register', 'booking:register', 'catalog:boot', 'booking:boot'],
            $log->getArrayCopy()
        );
        // So boot() may use a service that a later module registered.
        self::assertTrue($catalog->sawServiceAtBoot);
    }

    public function testEveryModuleGetsTheSameContext(): void
    {
        $catalog = new SampleModule('catalog');
        $booking = new SampleModule('booking');

        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot($catalog, $booking);

        self::assertNotNull($catalog->context);
        self::assertSame($catalog->context, $booking->context);
        self::assertSame('/path/to/plugin.php', $catalog->context->pluginFile);
        self::assertSame('1.2.3', $catalog->context->version);
    }

    public function testBootsWithNoModules(): void
    {
        $this->expectNotToPerformAssertions();

        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot();
    }

    public function testBootRunsPendingMigrationsBeforeAnyModuleBoots(): void
    {
        /** @var ArrayObject<int, string> $log */
        $log = new ArrayObject();
        $catalog = new SampleModule('catalog', $log, migrations: [new RecordingMigration('catalog 1', $log)]);

        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot($catalog);

        self::assertSame(['catalog:register', 'catalog 1', 'catalog:boot'], $log->getArrayCopy());
        self::assertSame(['kernel' => 2, 'catalog' => 1], $this->options[Options::key('db_versions')]);
    }

    public function testTheKernelMigratesItsOwnTablesFirst(): void
    {
        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot(new SampleModule('catalog'));

        self::assertSame(['kernel' => 2], $this->options[Options::key('db_versions')]);
        $queries = \implode("
", $this->queries);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `' . Tables::name('rate_limits') . '`', $queries);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `' . Tables::name('logs') . '`', $queries);
    }

    public function testBootWithACurrentSchemaDoesNotTouchTheDatabase(): void
    {
        $this->options[Options::key('db_versions')] = ['kernel' => 2];

        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot(new SampleModule('catalog'));

        self::assertSame([], $this->queries);
    }

    public function testAFailedMigrationOnBootPausesThePluginInsteadOfBreakingTheSite(): void
    {
        /** @var ArrayObject<int, string> $log */
        $log = new ArrayObject();
        $failing = new RecordingMigration('catalog 1', $log, fails: true);
        $catalog = new SampleModule('catalog', $log, migrations: [$failing]);
        $errorLog = \tempnam(\sys_get_temp_dir(), 'log');
        self::assertIsString($errorLog);
        $previous = \ini_set('error_log', $errorLog);

        try {
            (new Plugin('/path/to/plugin.php', '1.2.3'))->boot($catalog);
        } finally {
            \ini_set('error_log', (string) $previous);
        }

        self::assertSame(['catalog:register', 'catalog 1'], $log->getArrayCopy());
        self::assertNotFalse(\has_action('admin_notices'));
        $logged = (string) \file_get_contents($errorLog);
        \unlink($errorLog);
        self::assertStringContainsString('a database migration failed', $logged);
        // The server text may quote user data; only the generic message is logged.
        self::assertStringNotContainsString('Duplicate column', $logged);
    }

    public function testAFailedMigrationOnActivationStopsTheActivation(): void
    {
        /** @var ArrayObject<int, string> $log */
        $log = new ArrayObject();
        $failing = new RecordingMigration('catalog 1', $log, fails: true);
        $catalog = new SampleModule('catalog', $log, migrations: [$failing]);

        $this->expectException(DbException::class);

        (new Plugin('/path/to/plugin.php', '1.2.3'))->activate($catalog);
    }

    public function testActivationRunsTheMigrationsWithoutBootingModules(): void
    {
        /** @var ArrayObject<int, string> $log */
        $log = new ArrayObject();
        $catalog = new SampleModule('catalog', $log, migrations: [new RecordingMigration('catalog 1', $log)]);

        (new Plugin('/path/to/plugin.php', '1.2.3'))->activate($catalog);

        self::assertSame(['catalog 1'], $log->getArrayCopy());
        self::assertSame(['kernel' => 2, 'catalog' => 1], $this->options[Options::key('db_versions')]);
    }

    public function testModulesGetTheSharedDatabaseServices(): void
    {
        $catalog = new SampleModule('catalog');

        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot($catalog);

        self::assertNotNull($catalog->context);
        $container = $catalog->context->container;
        self::assertSame($container->get(Db::class), $container->get(Db::class));
        self::assertInstanceOf(Transaction::class, $container->get(Transaction::class));
    }

    public function testModulesGetTheSharedRestServices(): void
    {
        $catalog = new SampleModule('catalog');

        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot($catalog);

        self::assertNotNull($catalog->context);
        $container = $catalog->context->container;
        self::assertSame($container->get(Router::class), $container->get(Router::class));
        self::assertSame($container->get(RequestId::class), $container->get(RequestId::class));
        self::assertInstanceOf(SystemClock::class, $container->get(Clock::class));
    }

    public function testModulesGetTheKernelServices(): void
    {
        Functions\when('wp_salt')->justReturn('salt');
        $catalog = new SampleModule('catalog');

        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot($catalog);

        self::assertNotNull($catalog->context);
        $container = $catalog->context->container;
        self::assertSame($container->get(Logger::class), $container->get(Logger::class));
        self::assertSame($container->get(Settings::class), $container->get(Settings::class));
        self::assertSame($container->get(SecretStore::class), $container->get(SecretStore::class));
        // Built from the general settings, here their defaults.
        self::assertSame('۱۴۰۵/۰۷/۰۳', $container->get(DateFormatter::class)->date(
            new \DateTimeImmutable('2026-09-25 10:00:00 UTC'),
            new \DateTimeZone('Asia/Tehran')
        ));
    }

    public function testBootGivesTheModulesCapabilitiesBeforeTheyBoot(): void
    {
        /** @var array<string, list<string>> $added */
        $added = [];
        $this->fakeRoles($added);
        $catalog = new SampleModule('catalog', capabilities: ['manage_services' => ['administrator']]);
        $booking = new SampleModule('booking', capabilities: ['manage_services' => ['editor']]);

        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot($catalog, $booking);

        // Two modules naming one capability: the roles add up.
        self::assertSame(
            ['administrator' => [Caps::name('manage_services')], 'editor' => [Caps::name('manage_services')]],
            $added
        );
    }

    public function testActivationGivesTheCapabilities(): void
    {
        /** @var array<string, list<string>> $added */
        $added = [];
        $this->fakeRoles($added);

        (new Plugin('/path/to/plugin.php', '1.2.3'))->activate(
            new SampleModule('catalog', capabilities: ['manage_services' => ['administrator']])
        );

        self::assertSame(['administrator' => [Caps::name('manage_services')]], $added);
    }

    public function testAFailedMigrationGivesNoCapabilities(): void
    {
        Functions\expect('get_role')->never();
        $failing = new RecordingMigration('catalog 1', new ArrayObject(), fails: true);
        $catalog = new SampleModule(
            'catalog',
            migrations: [$failing],
            capabilities: ['manage_services' => ['administrator']]
        );
        $errorLog = \tempnam(\sys_get_temp_dir(), 'log');
        self::assertIsString($errorLog);
        $previous = \ini_set('error_log', $errorLog);

        try {
            (new Plugin('/path/to/plugin.php', '1.2.3'))->boot($catalog);
        } finally {
            \ini_set('error_log', (string) $previous);
            \unlink($errorLog);
        }

        self::assertNull($catalog->context);
    }

    /**
     * @param array<string, list<string>> $added Role => capabilities added.
     */
    private function fakeRoles(array &$added): void
    {
        Functions\when('get_role')->alias(static function (string $name) use (&$added): \WP_Role {
            /** @var \WP_Role&\Mockery\MockInterface $role */
            $role = \Mockery::mock('WP_Role');
            $role->shouldReceive('add_cap')->andReturnUsing(static function (string $cap) use ($name, &$added): void {
                $added[$name][] = $cap;
            });

            return $role;
        });
    }
}
