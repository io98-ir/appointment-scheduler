<?php

declare(strict_types=1);

namespace Vaqtyar\Tools\Rename;

/**
 * Runs git, Composer and pnpm for the rename scripts, without a shell where possible.
 */
final class Shell
{
    public function __construct(private readonly string $cwd)
    {
    }

    /**
     * Runs a command and relays its output (stderr merged into stdout) in order.
     *
     * The child does not inherit our stdout/stderr: when they are a redirected
     * file, two processes writing to it overwrite each other's output.
     *
     * @param list<string>|string $command A string runs through the shell.
     */
    public function run(array|string $command): int
    {
        // ['redirect', 1] (PHP 7.4+) is missing from PHPStan's proc_open stub.
        $spec = [1 => ['pipe', 'w'], 2 => ['redirect', 1]];
        $process = \proc_open($command, $spec, $pipes, $this->cwd); // @phpstan-ignore argument.type
        if (!\is_resource($process)) {
            throw new RenameException('Cannot run ' . (\is_array($command) ? \implode(' ', $command) : $command) . '.');
        }
        while (!\feof($pipes[1])) {
            $chunk = \fread($pipes[1], 8192);
            if (false === $chunk) {
                break;
            }
            \fwrite(\STDOUT, $chunk);
        }
        \fclose($pipes[1]);

        return \proc_close($process);
    }

    /**
     * Composer sets COMPOSER_BINARY for its scripts; otherwise `composer` must be on PATH.
     *
     * @param list<string> $args
     */
    public function composer(array $args): int
    {
        $binary = \getenv('COMPOSER_BINARY');
        if (\is_string($binary) && '' !== $binary) {
            return $this->run([\PHP_BINARY, $binary, ...$args]);
        }

        // Through the shell: on Windows `composer` is a .bat file.
        return $this->run('composer ' . \implode(' ', \array_map('escapeshellarg', $args)));
    }

    /**
     * Through the shell: on Windows `pnpm` is a .cmd file.
     *
     * @param list<string> $args
     */
    public function pnpm(array $args): int
    {
        return $this->run('pnpm ' . \implode(' ', \array_map('escapeshellarg', $args)));
    }

    public function isClean(): bool
    {
        return '' === $this->capture(['git', 'status', '--porcelain']);
    }

    /**
     * Files git would commit: tracked and untracked, without ignored ones.
     *
     * @return list<string>
     */
    public function gitFiles(): array
    {
        $output = $this->capture(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard']);

        // An unmerged path is listed once per stage.
        $files = \array_unique(\array_filter(\explode("\0", $output), static fn (string $f): bool => '' !== $f));
        \sort($files);

        return $files;
    }

    /**
     * @param list<string> $command
     */
    private function capture(array $command): string
    {
        $process = \proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->cwd);
        if (!\is_resource($process)) {
            throw new RenameException('Cannot run ' . $command[0] . '.');
        }
        $output = (string) \stream_get_contents($pipes[1]);
        $error = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        if (0 !== \proc_close($process)) {
            throw new RenameException(
                \implode(' ', $command) . ' failed (is this a git working tree?): ' . \trim($error)
            );
        }

        return $output;
    }
}
