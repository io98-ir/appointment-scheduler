<?php

/**
 * Boots the WordPress test library and loads the plugin as a must-use plugin,
 * the way the library expects. Needs WP_TESTS_DIR, which wp-env sets in its
 * containers; there is no local fallback (no Docker locally, dev-environment §1).
 */

declare(strict_types=1);

require \dirname(__DIR__, 2) . '/vendor/autoload.php';

$testsDir = \getenv('WP_TESTS_DIR');
if (!\is_string($testsDir) || !\is_file("$testsDir/includes/functions.php")) {
    \fwrite(\STDERR, "WP_TESTS_DIR does not point to the WordPress test library. Run this suite inside wp-env.\n");
    exit(1);
}

// wp-env derives the test library's config from the site's wp-config.php, with
// the same table prefix, and the library drops and reinstalls those tables.
// Our config uses its own prefix so a test run never wipes the dev site.
\define('WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php');

require_once "$testsDir/includes/functions.php";

\tests_add_filter(
    'muplugins_loaded',
    static function (): void {
        require \dirname(__DIR__, 2) . '/vaqtyar.php';
        // An online gateway the tests control; the registry reads the filter when a request first needs it.
        \add_filter(
            \Vaqtyar\Kernel\Hooks::name('payments/gateways'),
            static fn (array $gateways): array => [...$gateways, new \Vaqtyar\Tests\Fixtures\FakeGateway()]
        );
    }
);

require "$testsDir/includes/bootstrap.php";
