<?php

/**
 * The config wp-env generates for the test library, with a table prefix of its
 * own (see bootstrap.php). The library also includes this file from a separate
 * install process, so it must not depend on anything bootstrap.php sets up.
 */

declare(strict_types=1);

require \getenv('WP_TESTS_DIR') . '/wp-tests-config.php';

$table_prefix = 'wptests_';
