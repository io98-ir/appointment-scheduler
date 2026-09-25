<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Capabilities;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\KernelException;
use Vaqtyar\Kernel\Options;

final class CapabilitiesTest extends TestCase
{
    /** @var array<string, array{mixed, bool|null}> Option => value and autoload. */
    private array $options = [];

    /** @var array<string, list<string>> Role => capabilities added. */
    private array $added = [];

    private int $optionWrites = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('get_option')->alias(
            fn (string $name, mixed $default = false): mixed => isset($this->options[$name])
                ? $this->options[$name][0]
                : $default
        );
        Functions\when('update_option')->alias(function (string $name, mixed $value, ?bool $autoload = null): bool {
            $this->options[$name] = [$value, $autoload];
            $this->optionWrites++;

            return true;
        });
        Functions\when('get_role')->alias(function (string $name): ?\WP_Role {
            if (!\in_array($name, ['administrator', 'editor'], true)) {
                return null;
            }
            /** @var \WP_Role&\Mockery\MockInterface $role */
            $role = Mockery::mock('WP_Role');
            $role->shouldReceive('add_cap')->andReturnUsing(function (string $cap) use ($name): void {
                $this->added[$name][] = $cap;
            });

            return $role;
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testGivesEachCapabilityToItsRoles(): void
    {
        (new Capabilities())->grant([
            'manage_services' => ['administrator'],
            'view_calendar' => ['administrator', 'editor'],
        ]);

        self::assertSame([
            'administrator' => [Caps::name('manage_services'), Caps::name('view_calendar')],
            'editor' => [Caps::name('view_calendar')],
        ], $this->added);
        // Checked on every request: autoloaded.
        self::assertTrue($this->options[Options::key('granted_caps')][1]);
    }

    public function testGivesEachCapabilityOnlyOnce(): void
    {
        $capabilities = new Capabilities();
        $capabilities->grant(['manage_services' => ['administrator']]);

        // A later request, after the site owner took it from the role.
        $this->added = [];
        $capabilities->grant(['manage_services' => ['administrator']]);

        self::assertSame([], $this->added);
        self::assertSame(1, $this->optionWrites);
    }

    public function testANewCapabilityInAnUpdateIsGiven(): void
    {
        $capabilities = new Capabilities();
        $capabilities->grant(['manage_services' => ['administrator']]);

        $capabilities->grant(['manage_services' => ['administrator'], 'manage_staff' => ['administrator']]);

        self::assertSame(
            ['administrator' => [Caps::name('manage_services'), Caps::name('manage_staff')]],
            $this->added
        );
    }

    public function testARoleThatDoesNotExistYetGetsItLater(): void
    {
        $capabilities = new Capabilities();
        $capabilities->grant(['view_calendar' => ['shop_manager']]);
        self::assertSame([], $this->added);
        self::assertArrayNotHasKey(Options::key('granted_caps'), $this->options);

        Functions\when('get_role')->alias(function (string $name): \WP_Role {
            /** @var \WP_Role&\Mockery\MockInterface $role */
            $role = Mockery::mock('WP_Role');
            $role->shouldReceive('add_cap')->andReturnUsing(function (string $cap) use ($name): void {
                $this->added[$name][] = $cap;
            });

            return $role;
        });
        $capabilities->grant(['view_calendar' => ['shop_manager']]);

        self::assertSame(['shop_manager' => [Caps::name('view_calendar')]], $this->added);
    }

    public function testNoCapabilitiesSendsNoQuery(): void
    {
        Functions\expect('get_option')->never();

        (new Capabilities())->grant([]);

        self::assertSame(0, $this->optionWrites);
    }

    public function testRejectsAnInvalidName(): void
    {
        $this->expectException(KernelException::class);

        (new Capabilities())->grant(['Manage Services' => ['administrator']]);
    }
}
