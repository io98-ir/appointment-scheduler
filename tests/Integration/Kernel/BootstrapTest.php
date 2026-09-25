<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel;

use Vaqtyar\Kernel\Requirements;

/**
 * The plugin loads on a real WordPress with a real database server.
 */
final class BootstrapTest extends \WP_UnitTestCase
{
    public function testRequirementsPassOnTheTestEnvironment(): void
    {
        $wpdb = $GLOBALS['wpdb'];
        if (!$wpdb instanceof \wpdb) {
            self::fail('$wpdb is not set up.');
        }

        // The server version comes from the real MySQL or MariaDB handshake.
        self::assertSame([], Requirements::failures(
            \PHP_VERSION,
            \get_bloginfo('version'),
            \get_loaded_extensions(),
            (string) $wpdb->db_server_info(),
            true
        ));
    }

    public function testActionSchedulerIsLoadedFromTheMainFile(): void
    {
        self::assertTrue(\function_exists('as_enqueue_async_action'));
        self::assertSame(1, \did_action('action_scheduler_init'));
    }
}
