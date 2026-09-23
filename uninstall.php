<?php

/**
 * WordPress runs this file when the plugin is deleted, even on a host that
 * fails the requirements check, so it must parse on old PHP as well.
 *
 * Plugin data is removed only when the site owner has explicitly opted in
 * (docs/03-architecture/02-architecture.md §5). Until the plugin stores any
 * data there is nothing to remove.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;
