<?php

/**
 * Plugin Name:       Vaqtyar
 * Description:       Appointment booking for WordPress.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vaqtyar
 * Domain Path:       /languages
 * Update URI:        false
 */

declare(strict_types=1);

// This file must parse on old PHP so that an unsupported host gets a notice
// instead of a fatal error: PHP 7.0 syntax only. See
// docs/04-engineering/03-implementation-notes.md §1.

defined('ABSPATH') || exit;

define('VAQTYAR_VERSION', '0.1.0');
define('VAQTYAR_FILE', __FILE__);

require_once __DIR__ . '/src/Kernel/Requirements.php';

if (!\Vaqtyar\Kernel\Requirements::met(__FILE__)) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

// Must load from the main file, before plugins_loaded: Action Scheduler picks
// the newest copy among all active plugins at that point.
require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

// Composition root: the modules are listed once, here, so the kernel depends
// on none and boot and activation see the same list.
$vaqtyar_modules = static function () {
    return array(
        new \Vaqtyar\Modules\Admin\AdminModule(),
    );
};

// On the activation request plugins_loaded has already fired when this file is
// included, so activation must not rely on boot() (implementation-notes §1).
register_activation_hook(
    VAQTYAR_FILE,
    static function () use ($vaqtyar_modules) {
        (new \Vaqtyar\Kernel\Plugin(VAQTYAR_FILE, VAQTYAR_VERSION))->activate(...$vaqtyar_modules());
    }
);

add_action(
    'plugins_loaded',
    static function () use ($vaqtyar_modules) {
        (new \Vaqtyar\Kernel\Plugin(VAQTYAR_FILE, VAQTYAR_VERSION))->boot(...$vaqtyar_modules());
    },
    5
);
