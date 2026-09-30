<?php

/**
 * Builds the release zip (T6.5/T6.6): `pnpm build` first, then
 *
 *   php tools/build-zip.php
 *
 * It writes dist/{slug}-{version}.zip and leaves the folder it zipped at
 * dist/{slug}/, which is what the plugin-check CI job installs. It fails,
 * naming the file, when the zip would hold anything but the plugin, or the
 * version differs between the plugin header, its constant, readme.txt and
 * CHANGELOG.md.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Vaqtyar\Tools\Release\Packager;
use Vaqtyar\Tools\Release\ReleaseException;

exit((static function (): int {
    $root = \dirname(__DIR__);
    try {
        $packager = Packager::fromRoot($root);
        $zip = $packager->build($root . '/dist');
    } catch (ReleaseException $e) {
        \fwrite(\STDERR, 'Release failed: ' . $e->getMessage() . "\n");

        return 1;
    }
    \fwrite(\STDOUT, \sprintf("Built %s (%.1f MB)\n", \str_replace($root . '/', '', $zip), \filesize($zip) / 1048576));

    return 0;
})());
