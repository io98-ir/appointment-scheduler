<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Database;

use ArrayObject;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Database\Migrator;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Tests\Unit\Kernel\Fixtures\RecordingMigration;

/**
 * Ordering, version bookkeeping and the migration lock. Real DDL and GET_LOCK
 * run in the integration suite.
 */
final class MigratorTest extends TestCase
{
    use FakesWpdb;

    /** @var array<string, mixed> The options table. */
    private array $options = [];

    /** @var array<string, mixed>|null The alloptions cache, loaded on first read, as in WordPress. */
    private ?array $cache = null;

    /** @var ArrayObject<int, string> */
    private ArrayObject $ran;

    private Migrator $migrator;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('get_option')->alias(function (string $name, mixed $default = false): mixed {
            $this->cache ??= $this->options;

            return $this->cache[$name] ?? $default;
        });
        Functions\when('update_option')->alias(function (string $name, mixed $value): bool {
            $this->options[$name] = $value;
            if (null !== $this->cache) {
                $this->cache[$name] = $value;
            }

            return true;
        });
        Functions\when('wp_cache_delete')->alias(function (string $key, string $group): bool {
            if ('alloptions' === $key && 'options' === $group) {
                $this->cache = null;
            }

            return true;
        });

        $this->ran = new ArrayObject();
        $this->migrator = new Migrator(new Db($this->fakeWpdb()));
        $this->respondWith(static fn (string $sql): string|bool => \str_contains($sql, 'GET_LOCK') ? '1' : true);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $this->tearDownWpdb();
        parent::tearDown();
    }

    public function testRunsEveryOwnersMigrationsInOrderAndRecordsTheVersions(): void
    {
        $this->assertTrue($this->migrator->migrate([
            'catalog' => [$this->migration('catalog 1'), $this->migration('catalog 2')],
            'booking' => [$this->migration('booking 1')],
        ]));

        self::assertSame(['catalog 1', 'catalog 2', 'booking 1'], $this->ran->getArrayCopy());
        self::assertSame(['catalog' => 2, 'booking' => 1], $this->versions());
    }

    public function testRunsOnlyTheMigrationsAddedSinceTheLastRun(): void
    {
        $this->options[Options::key('db_versions')] = ['catalog' => 1];

        $this->migrator->migrate([
            'catalog' => [$this->migration('catalog 1'), $this->migration('catalog 2')],
            'booking' => [$this->migration('booking 1')],
        ]);

        self::assertSame(['catalog 2', 'booking 1'], $this->ran->getArrayCopy());
        self::assertSame(['catalog' => 2, 'booking' => 1], $this->versions());
    }

    public function testDoesNothingAndTakesNoLockWhenCurrent(): void
    {
        $this->options[Options::key('db_versions')] = ['catalog' => 1];
        $migrations = ['catalog' => [$this->migration('catalog 1')]];

        self::assertTrue($this->migrator->isCurrent($migrations));
        self::assertTrue($this->migrator->migrate($migrations));
        self::assertSame([], $this->ran->getArrayCopy());
        self::assertSame([], $this->queries);
    }

    public function testANewerStoredVersionIsNotAnError(): void
    {
        // The plugin was downgraded: migrations have no down(), so nothing runs.
        $this->options[Options::key('db_versions')] = ['catalog' => 5];
        $migrations = ['catalog' => [$this->migration('catalog 1')]];

        self::assertTrue($this->migrator->isCurrent($migrations));
        $this->migrator->migrate($migrations);
        self::assertSame([], $this->ran->getArrayCopy());
    }

    public function testRunsNothingWhileAnotherRequestHoldsTheLock(): void
    {
        $this->respondWith(static fn (string $sql): string|bool => \str_contains($sql, 'GET_LOCK') ? '0' : true);

        self::assertFalse($this->migrator->migrate(['catalog' => [$this->migration('catalog 1')]]));
        self::assertSame([], $this->ran->getArrayCopy());
        self::assertSame([], $this->versions());
    }

    public function testRereadsTheVersionsOnceItHasTheLock(): void
    {
        // Another request finished the work between the check and the lock.
        $this->respondWith(function (string $sql): string|bool {
            if (\str_contains($sql, 'GET_LOCK')) {
                $this->options[Options::key('db_versions')] = ['catalog' => 1];

                return '1';
            }

            return true;
        });

        $this->migrator->migrate(['catalog' => [$this->migration('catalog 1')]]);

        self::assertSame([], $this->ran->getArrayCopy());
    }

    public function testAFailedMigrationKeepsTheProgressBeforeItAndReleasesTheLock(): void
    {
        try {
            $this->migrator->migrate([
                'catalog' => [
                    $this->migration('catalog 1'),
                    $this->migration('catalog 2', fails: true),
                    $this->migration('catalog 3'),
                ],
            ]);
            self::fail('The failure was swallowed.');
        } catch (DbException) {
            // Expected: the state afterwards is what matters.
        }

        self::assertSame(['catalog 1', 'catalog 2'], $this->ran->getArrayCopy());
        self::assertSame(['catalog' => 1], $this->versions());
        self::assertStringContainsString('RELEASE_LOCK', (string) \end($this->queries));
    }

    public function testTheLockNameIsPerSite(): void
    {
        $this->migrator->migrate(['catalog' => [$this->migration('catalog 1')]]);
        $this->wpdb->prefix = 'wp_2_';
        $this->migrator->migrate(['catalog' => [$this->migration('catalog 1'), $this->migration('catalog 2')]]);

        $locks = \array_values(\array_filter(
            $this->queries,
            static fn (string $sql): bool => \str_contains($sql, 'GET_LOCK')
        ));
        self::assertCount(2, $locks);
        self::assertStringContainsString("'.wp_", $locks[0]);
        self::assertStringContainsString("'.wp_2_", $locks[1]);
    }

    public function testIgnoresAStoredValueOfTheWrongShape(): void
    {
        $this->options[Options::key('db_versions')] = 'corrupt';

        self::assertFalse($this->migrator->isCurrent(['catalog' => [$this->migration('catalog 1')]]));
    }

    private function migration(string $name, bool $fails = false): Migration
    {
        return new RecordingMigration($name, $this->ran, $fails);
    }

    private function versions(): mixed
    {
        return $this->options[Options::key('db_versions')] ?? [];
    }
}
