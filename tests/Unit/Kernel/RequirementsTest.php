<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Requirements;

final class RequirementsTest extends TestCase
{
    private const OK_EXTENSIONS = ['Core', 'json', 'mbstring'];
    private const MYSQL = '8.0.36';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_version']);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testNoFailuresWhenEverythingIsSatisfied(): void
    {
        self::assertSame([], Requirements::failures('8.1.0', '6.6', self::OK_EXTENSIONS, self::MYSQL, true));
        self::assertSame([], Requirements::failures('8.4.12', '7.0.1', self::OK_EXTENSIONS, self::MYSQL, true));
    }

    public function testOldPhpFails(): void
    {
        self::assertSame(
            [['type' => 'php', 'required' => '8.1', 'found' => '8.0.30']],
            Requirements::failures('8.0.30', '6.6', self::OK_EXTENSIONS, self::MYSQL, true)
        );
    }

    public function testOldWordPressFails(): void
    {
        self::assertSame(
            [['type' => 'wp', 'required' => '6.6', 'found' => '6.5.5']],
            Requirements::failures('8.1.0', '6.5.5', self::OK_EXTENSIONS, self::MYSQL, true)
        );
    }

    public function testWordPressPreReleaseOfRequiredVersionPasses(): void
    {
        // Same rule as core's is_wp_version_compatible(): the suffix after "-" is ignored.
        self::assertSame([], Requirements::failures('8.1.0', '6.6-RC1', self::OK_EXTENSIONS, self::MYSQL, true));
        self::assertSame([], Requirements::failures('8.1.0', '6.6-alpha-58000-src', self::OK_EXTENSIONS, self::MYSQL, true));
    }

    public function testMissingExtensionFails(): void
    {
        self::assertSame(
            [['type' => 'extension', 'required' => 'mbstring', 'found' => '']],
            Requirements::failures('8.1.0', '6.6', ['Core', 'json'], self::MYSQL, true)
        );
    }

    public function testExtensionNamesAreCaseInsensitive(): void
    {
        self::assertSame([], Requirements::failures('8.1.0', '6.6', ['MBString'], self::MYSQL, true));
    }

    /**
     * @return array<string, array{0: string, 1: array<int, array{type: string, required: string, found: string}>}>
     */
    public static function databaseVersions(): array
    {
        return [
            'MySQL 5.7' => ['5.7.44', []],
            'MySQL 5.6' => ['5.6.51-log', [['type' => 'mysql', 'required' => '5.7', 'found' => '5.6.51']]],
            'MariaDB 10.4' => ['10.4.34-MariaDB', []],
            'MariaDB 10.3 with 5.5.5 prefix' => [
                '5.5.5-10.3.39-MariaDB-0+deb10u1',
                [['type' => 'mariadb', 'required' => '10.4', 'found' => '10.3.39']],
            ],
            'MariaDB 11 with 5.5.5 prefix' => ['5.5.5-11.4.2-MariaDB', []],
            'unknown server info is not judged' => ['', []],
        ];
    }

    /**
     * @dataProvider databaseVersions
     * @param array<int, array{type: string, required: string, found: string}> $expected
     */
    public function testDatabaseVersion(string $serverInfo, array $expected): void
    {
        self::assertSame($expected, Requirements::failures('8.1.0', '6.6', self::OK_EXTENSIONS, $serverInfo, true));
    }

    public function testMissingVendorFails(): void
    {
        self::assertSame(
            [['type' => 'vendor', 'required' => '', 'found' => '']],
            Requirements::failures('8.1.0', '6.6', self::OK_EXTENSIONS, self::MYSQL, false)
        );
    }

    public function testAllFailuresAreReportedTogether(): void
    {
        $types = array_column(Requirements::failures('7.4.33', '6.0', [], '5.6.51', false), 'type');

        self::assertSame(['php', 'wp', 'extension', 'mysql', 'vendor'], $types);
    }

    public function testMetReturnsTrueAndAddsNoNoticeOnThisEnvironment(): void
    {
        $GLOBALS['wp_version'] = '6.6';
        Actions\expectAdded('admin_notices')->never();

        self::assertTrue(Requirements::met(dirname(__DIR__, 3) . '/vaqtyar.php'));
    }

    public function testMetReturnsFalseAndHooksNoticeWhenWordPressIsTooOld(): void
    {
        $GLOBALS['wp_version'] = '6.5';
        Actions\expectAdded('admin_notices')->once();
        Actions\expectAdded('network_admin_notices')->once();

        self::assertFalse(Requirements::met(dirname(__DIR__, 3) . '/vaqtyar.php'));
    }

    public function testNoticeListsEachFailureEscaped(): void
    {
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_file_data')->justReturn(['name' => 'Acme <Booking>']);

        $this->expectOutputRegex(
            '~^<div class="notice notice-error">'
            . '<p>Acme &lt;Booking&gt; requires PHP 8\.1 or newer\. This site runs PHP 8\.0\.30\.</p>'
            . '<p>Acme &lt;Booking&gt; requires WordPress 6\.6 or newer\. This site runs WordPress 6\.5\.5\.</p>'
            . '<p>Acme &lt;Booking&gt; requires the PHP extension mbstring\.</p>'
            . '<p>Acme &lt;Booking&gt; requires MySQL 5\.7 or newer\. This site runs MySQL 5\.6\.51\.</p>'
            . '<p>Acme &lt;Booking&gt; requires MariaDB 10\.4 or newer\. This site runs MariaDB 10\.3\.39\.</p>'
            . '<p>Acme &lt;Booking&gt; is missing its bundled libraries \(vendor directory\)\. '
            . 'Reinstall it from the release package, or run composer install\.</p>'
            . '<p>Acme &lt;Booking&gt; will not run until this is fixed\.</p>'
            . '</div>$~'
        );

        Requirements::renderNotice('/plugin/main.php', [
            ['type' => 'php', 'required' => '8.1', 'found' => '8.0.30'],
            ['type' => 'wp', 'required' => '6.6', 'found' => '6.5.5'],
            ['type' => 'extension', 'required' => 'mbstring', 'found' => ''],
            ['type' => 'mysql', 'required' => '5.7', 'found' => '5.6.51'],
            ['type' => 'mariadb', 'required' => '10.4', 'found' => '10.3.39'],
            ['type' => 'vendor', 'required' => '', 'found' => ''],
        ]);
    }

    public function testNoticeIsHiddenFromUsersWhoCannotManagePlugins(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        $this->expectOutputString('');

        Requirements::renderNotice('/plugin/main.php', [
            ['type' => 'php', 'required' => '8.1', 'found' => '8.0.30'],
        ]);
    }
}
