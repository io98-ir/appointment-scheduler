<?php

/**
 * Loaded by the main plugin file before anything else, so it must parse on
 * old PHP: PHP 7.0 syntax only (no typed properties, nullable or void types,
 * const visibility, match, enum, named arguments or ?->). See
 * docs/04-engineering/03-implementation-notes.md §1.
 */

declare(strict_types=1);

namespace Vaqtyar\Kernel;

final class Requirements
{
    const MIN_PHP = '8.1';
    const MIN_WP = '6.6';
    const MIN_MYSQL = '5.7';
    const MIN_MARIADB = '10.4';
    const EXTENSIONS = array('mbstring');

    /**
     * Checks the running environment. On failure, hooks an admin notice and
     * returns false so the caller can stop loading the plugin.
     */
    public static function met(string $pluginFile): bool
    {
        $wpVersion = isset($GLOBALS['wp_version']) && \is_string($GLOBALS['wp_version']) ? $GLOBALS['wp_version'] : '0';
        // db_server_info() reads the connection handshake; it runs no query.
        $dbServerInfo = isset($GLOBALS['wpdb']) && $GLOBALS['wpdb'] instanceof \wpdb
            ? (string) $GLOBALS['wpdb']->db_server_info()
            : '';
        $failures = self::failures(
            PHP_VERSION,
            $wpVersion,
            \get_loaded_extensions(),
            $dbServerInfo,
            \is_readable(\dirname($pluginFile) . '/vendor/autoload.php')
        );

        if (array() === $failures) {
            return true;
        }

        $notice = function () use ($pluginFile, $failures) {
            // The plugin never boots on this path, so nothing else loads its translations.
            \load_plugin_textdomain('vaqtyar', false, \dirname(\plugin_basename($pluginFile)) . '/languages');
            self::renderNotice($pluginFile, $failures);
        };
        \add_action('admin_notices', $notice);
        \add_action('network_admin_notices', $notice);

        return false;
    }

    /**
     * The InnoDB engine is not checked here: that needs a query, so the
     * Migrator verifies it when it creates tables.
     *
     * @param string[] $loadedExtensions
     * @param string $dbServerInfo As returned by wpdb::db_server_info(); empty if unknown.
     * @return array<int, array{type: string, required: string, found: string}>
     */
    public static function failures(
        string $phpVersion,
        string $wpVersion,
        array $loadedExtensions,
        string $dbServerInfo,
        bool $vendorInstalled
    ): array {
        $failures = array();

        if (\version_compare($phpVersion, self::MIN_PHP, '<')) {
            $failures[] = array('type' => 'php', 'required' => self::MIN_PHP, 'found' => $phpVersion);
        }

        // Same rule as core's is_wp_version_compatible(): ignore "-RC1", "-src" and the like.
        $wpBase = \explode('-', $wpVersion)[0];
        if (\version_compare($wpBase, self::MIN_WP, '<')) {
            $failures[] = array('type' => 'wp', 'required' => self::MIN_WP, 'found' => $wpVersion);
        }

        $loaded = \array_map('strtolower', $loadedExtensions);
        foreach (self::EXTENSIONS as $extension) {
            if (!\in_array($extension, $loaded, true)) {
                $failures[] = array('type' => 'extension', 'required' => $extension, 'found' => '');
            }
        }

        $failures = \array_merge($failures, self::databaseFailures($dbServerInfo));

        if (!$vendorInstalled) {
            $failures[] = array('type' => 'vendor', 'required' => '', 'found' => '');
        }

        return $failures;
    }

    /**
     * @return array<int, array{type: string, required: string, found: string}>
     */
    private static function databaseFailures(string $serverInfo): array
    {
        // MariaDB used to report itself as "5.5.5-10.x.y-MariaDB" for client compatibility.
        if (0 === \strpos($serverInfo, '5.5.5-')) {
            $serverInfo = \substr($serverInfo, 6);
        }
        if (1 !== \preg_match('/^(\d+\.\d+(?:\.\d+)?)/', $serverInfo, $matches)) {
            return array();
        }

        $isMariaDb = false !== \stripos($serverInfo, 'mariadb');
        $minimum = $isMariaDb ? self::MIN_MARIADB : self::MIN_MYSQL;
        if (\version_compare($matches[1], $minimum, '>=')) {
            return array();
        }

        return array(array('type' => $isMariaDb ? 'mariadb' : 'mysql', 'required' => $minimum, 'found' => $matches[1]));
    }

    /**
     * Prints the notice. Runs inside admin_notices, i.e. after init, so
     * translation calls are safe here.
     *
     * @param array<int, array{type: string, required: string, found: string}> $failures
     * @return void The return type cannot be declared: `void` is PHP 7.1+.
     */
    public static function renderNotice(string $pluginFile, array $failures)
    {
        if (!\current_user_can('activate_plugins')) {
            return;
        }

        $headers = \get_file_data($pluginFile, array('name' => 'Plugin Name'));
        $name = '' !== $headers['name'] ? $headers['name'] : \basename(\dirname($pluginFile));

        echo '<div class="notice notice-error">';
        foreach ($failures as $failure) {
            echo '<p>' . \esc_html(self::message($name, $failure)) . '</p>';
        }
        /* translators: %s: plugin name. */
        echo '<p>' . \esc_html(\sprintf(\__('%s will not run until this is fixed.', 'vaqtyar'), $name)) . '</p>';
        echo '</div>';
    }

    /**
     * @param array{type: string, required: string, found: string} $failure
     */
    private static function message(string $name, array $failure): string
    {
        switch ($failure['type']) {
            case 'php':
                /* translators: 1: plugin name, 2: required PHP version, 3: current PHP version. */
                $format = \__('%1$s requires PHP %2$s or newer. This site runs PHP %3$s.', 'vaqtyar');
                break;
            case 'wp':
                /* translators: 1: plugin name, 2: required WordPress version, 3: current WordPress version. */
                $format = \__('%1$s requires WordPress %2$s or newer. This site runs WordPress %3$s.', 'vaqtyar');
                break;
            case 'extension':
                /* translators: 1: plugin name, 2: PHP extension name. */
                $format = \__('%1$s requires the PHP extension %2$s.', 'vaqtyar');
                break;
            case 'mysql':
                /* translators: 1: plugin name, 2: required MySQL version, 3: current MySQL version. */
                $format = \__('%1$s requires MySQL %2$s or newer. This site runs MySQL %3$s.', 'vaqtyar');
                break;
            case 'mariadb':
                /* translators: 1: plugin name, 2: required MariaDB version, 3: current MariaDB version. */
                $format = \__('%1$s requires MariaDB %2$s or newer. This site runs MariaDB %3$s.', 'vaqtyar');
                break;
            case 'vendor':
            default:
                /* translators: %1$s: plugin name. */
                $format = \__(
                    // phpcs:ignore Generic.Files.LineLength.TooLong -- a translatable string is not split.
                    '%1$s is missing its bundled libraries (vendor directory). Reinstall it from the release package, or run composer install.',
                    'vaqtyar'
                );
        }

        return \sprintf($format, $name, $failure['required'], $failure['found']);
    }
}
