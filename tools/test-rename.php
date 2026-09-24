<?php

/**
 * Proves that the plugin can still be renamed (ADR-000): copies the working
 * tree to a temporary directory, renames it there, and runs `composer check`
 * on the result. Run it with `composer test:rename`; pass --keep to leave the
 * copy in place for inspection.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Vaqtyar\Tools\Rename\Shell;

exit((static function (array $args): int {
    $source = \dirname(__DIR__);
    $copy = \sys_get_temp_dir() . '/rename-check-' . \bin2hex(\random_bytes(4));
    $step = static function (string $title): void {
        \fwrite(\STDOUT, "\n== $title\n");
    };

    $step("Copying the working tree to $copy");
    foreach ((new Shell($source))->gitFiles() as $path) {
        // Skip deleted-but-uncommitted files, and the agent tooling, which the rename never touches.
        if (!\is_file("$source/$path") || \str_starts_with($path, '.claude/')) {
            continue;
        }
        if (!\is_dir(\dirname("$copy/$path"))) {
            \mkdir(\dirname("$copy/$path"), 0777, true);
        }
        \copy("$source/$path", "$copy/$path");
    }

    $shell = new Shell($copy);
    $steps = [
        // rename.php lists files with git and needs a clean working tree.
        'git init and commit' => static fn (): int => $shell->run(['git', 'init', '--quiet'])
            ?: $shell->run(['git', 'add', '--all'])
            ?: $shell->run(
                ['git', '-c', 'user.name=rename', '-c', 'user.email=rename@localhost', 'commit', '-qm', 'copy']
            ),
        'composer install' => static fn (): int => $shell->composer(
            ['install', '--no-interaction', '--no-progress', '--quiet']
        ),
        // Built from pieces: rename.php refuses a new token that already appears in the code.
        'rename' => static fn (): int => $shell->run([
            \PHP_BINARY,
            'tools/rename.php',
            '--name=Rename Check',
            '--slug=' . 'rename' . 'check',
            '--namespace=' . 'Rename' . 'Check',
            '--prefix=' . 'rn' . 'c',
        ]),
        'composer check on the renamed copy' => static fn (): int => $shell->composer(['check']),
    ];

    $exit = 0;
    foreach ($steps as $title => $run) {
        $step($title);
        $exit = $run();
        if (0 !== $exit) {
            \fwrite(\STDERR, "\nFAILED at \"$title\" (exit $exit). The copy is kept at $copy\n");

            return $exit;
        }
    }

    if (\in_array('--keep', $args, true)) {
        \fwrite(\STDOUT, "\nOK. The renamed copy is at $copy\n");

        return 0;
    }
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($copy, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file instanceof \SplFileInfo) {
            // git marks its objects read-only, which blocks unlink() on Windows.
            \chmod($file->getPathname(), 0777);
            $file->isDir() && !$file->isLink() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
        }
    }
    \rmdir($copy);
    \fwrite(\STDOUT, "\nOK. The renamed copy passed composer check.\n");

    return 0;
})(\array_slice($argv, 1)));
