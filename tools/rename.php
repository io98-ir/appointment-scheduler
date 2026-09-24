<?php

/**
 * Changes the plugin's technical identity (ADR-000). Run from anywhere:
 *
 *   php tools/rename.php --name="Display Name" --slug=slug --namespace=Namespace --prefix=pfx --dry-run
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

exit((new Vaqtyar\Tools\Rename\RenameCommand(\dirname(__DIR__), \STDOUT, \STDERR))->run(\array_slice($argv, 1)));
