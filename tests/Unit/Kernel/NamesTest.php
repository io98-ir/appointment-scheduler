<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\KernelException;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Kernel\Tables;

/**
 * The naming helpers (ADR-000): every table, option, hook and capability name
 * is built from Identity, and names that end up unquoted in SQL are validated.
 */
final class NamesTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function testTableNameUsesTheSitePrefixAndThePluginPrefix(): void
    {
        self::setWpdbPrefix('wp_');
        self::assertSame('wp_' . Identity::PREFIX . '_appointments', Tables::name('appointments'));
    }

    public function testTableNameFollowsTheCurrentSitePrefix(): void
    {
        // Multisite: switch_to_blog() changes $wpdb->prefix, so it is read on every call.
        self::setWpdbPrefix('wp_3_');
        self::assertSame('wp_3_' . Identity::PREFIX . '_resource_day_locks', Tables::name('resource_day_locks'));
    }

    /**
     * @dataProvider invalidNames
     */
    public function testTableNameRejectsAnythingButLowercaseIdentifiers(string $table): void
    {
        self::setWpdbPrefix('wp_');
        $this->expectException(KernelException::class);

        Tables::name($table);
    }

    public function testTableNameRejectsNamesLongerThanMysqlAllows(): void
    {
        self::setWpdbPrefix('wp_');
        $fits = \str_repeat('a', 64 - \strlen('wp_' . Identity::PREFIX . '_'));
        self::assertSame(64, \strlen(Tables::name($fits)));

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('longer than 64');

        Tables::name($fits . 'a');
    }

    public function testTableNameFailsWithoutWpdb(): void
    {
        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('$wpdb is not available');

        Tables::name('appointments');
    }

    public function testOptionKeyIsPrefixedWithThePluginPrefix(): void
    {
        self::assertSame(Identity::PREFIX . '_db_versions', Options::key('db_versions'));
    }

    /**
     * @dataProvider invalidNames
     */
    public function testOptionKeyRejectsInvalidNames(string $name): void
    {
        $this->expectException(KernelException::class);

        Options::key($name);
    }

    public function testOptionKeyRejectsKeysLongerThanTheOptionNameColumn(): void
    {
        $fits = \str_repeat('a', 191 - \strlen(Identity::PREFIX . '_'));
        self::assertSame(191, \strlen(Options::key($fits)));

        $this->expectException(KernelException::class);

        Options::key($fits . 'a');
    }

    public function testHookNameIsPrefixedWithTheHookPrefix(): void
    {
        self::assertSame(
            Identity::HOOK_PREFIX . '/booking/appointment_confirmed',
            Hooks::name('booking/appointment_confirmed')
        );
        self::assertSame(Identity::HOOK_PREFIX . '/modules/register', Hooks::name('modules/register'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHookNames(): iterable
    {
        yield 'empty' => [''];
        yield 'leading slash' => ['/booking/confirmed'];
        yield 'trailing slash' => ['booking/'];
        yield 'double slash' => ['booking//confirmed'];
        yield 'uppercase' => ['Booking/confirmed'];
        yield 'space' => ['booking/appointment confirmed'];
        yield 'trailing newline' => ["booking/confirmed\n"];
    }

    /**
     * @dataProvider invalidHookNames
     */
    public function testHookNameRejectsMalformedNames(string $name): void
    {
        $this->expectException(KernelException::class);

        Hooks::name($name);
    }

    public function testCapabilityNameIsPrefixedWithThePluginPrefix(): void
    {
        self::assertSame(Identity::PREFIX . '_manage_bookings', Caps::name('manage_bookings'));
    }

    /**
     * @dataProvider invalidNames
     */
    public function testCapabilityNameRejectsInvalidNames(string $name): void
    {
        $this->expectException(KernelException::class);

        Caps::name($name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Appointments'];
        yield 'leading digit' => ['1appointments'];
        yield 'leading underscore' => ['_appointments'];
        yield 'hyphen' => ['day-locks'];
        yield 'space' => ['day locks'];
        yield 'backtick' => ['x`; DROP TABLE wp_users; --'];
        yield 'dot' => ['wp.users'];
        yield 'non-ascii' => ['نوبت'];
        // "$" alone would match before a final newline.
        yield 'trailing newline' => ["appointments\n"];
    }

    private static function setWpdbPrefix(string $prefix): void
    {
        $wpdb = new \stdClass();
        $wpdb->prefix = $prefix;
        $GLOBALS['wpdb'] = $wpdb;
    }
}
