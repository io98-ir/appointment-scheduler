<?php

declare(strict_types=1);

namespace Vaqtyar\Tools\Rename;

/**
 * Runs git and Composer for the rename scripts, without a shell where possible.
 */
final class Shell
{
    public function __construct(private readonly string $cwd)
    {
    }

    /**
     * @param list<string> $command
     */
    public function run(array $command): int
    {
        $process = \proc_open($command, [\STDIN, \STDOUT, \STDERR], $pipes, $this->cwd);
        if (!\is_resource($process)) {
            throw new RenameException('Cannot run ' . \implode(' ', $command) . '.');
        }

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
        $process = \proc_open(
            'composer ' . \implode(' ', \array_map('escapeshellarg', $args)),
            [\STDIN, \STDOUT, \STDERR],
            $pipes,
            $this->cwd
        );
        if (!\is_resource($process)) {
            throw new RenameException('Cannot run composer.');
        }

        return \proc_close($process);
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
