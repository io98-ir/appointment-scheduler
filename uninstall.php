<?php

/**
 * WordPress runs this file when the plugin is deleted, even on a host that
 * fails the requirements check, so it must parse on old PHP as well.
 *
 * Plugin data is removed only when the site owner has explicitly opted in
 * (docs/03-architecture/02-architecture.md §5): the Uninstaller reads that
 * choice and does nothing without it.
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

require_once __DIR__ . '/src/Kernel/Requirements.php';

// On a host that cannot run the plugin there is nothing of it to load, so nothing to remove safely.
if (
    version_compare((string) phpversion(), \Vaqtyar\Kernel\Requirements::MIN_PHP, '<')
    || !is_readable(__DIR__ . '/vendor/autoload.php')
) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

\Vaqtyar\Kernel\Uninstaller::run();
