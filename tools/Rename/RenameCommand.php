<?php

declare(strict_types=1);

namespace Vaqtyar\Tools\Rename;

/**
 * `php tools/rename.php --name="…" --slug=… --namespace=… --prefix=… [--dry-run]`
 *
 * Omitted options keep their current value. See ADR-000.
 */
final class RenameCommand
{
    private const USAGE = <<<'TXT'
        Usage: php tools/rename.php [--name="Display Name"] [--slug=slug] [--namespace=Namespace]
                                    [--prefix=pfx] [--dry-run]

          --name       Default brand name (plugin header, Identity::NAME). Any text.
          --slug       Main file, text domain, hook prefix, constant prefix, REST namespace. a-z0-9, 3-32.
          --namespace  PHP namespace. A-Za-z0-9, 3-32, starting with a capital.
          --prefix     Tables, options and capabilities. a-z0-9, 3-6.
          --dry-run    Show what would change and stop.

        Only allowed before the first public release (ADR-000).

        TXT;

    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    /**
     * @param resource $out
     * @param resource $err
     */
    public function __construct(private readonly string $root, $out, $err)
    {
        $this->out = $out;
        $this->err = $err;
    }

    /**
     * @param list<string> $args Command-line arguments without the script name.
     */
    public function run(array $args): int
    {
        try {
            return $this->execute(self::parse($args));
        } catch (RenameException | \JsonException $e) {
            \fwrite($this->err, 'Error: ' . $e->getMessage() . "\n");

            return 1;
        }
    }

    /**
     * @param array<string, string|true> $options
     */
    private function execute(array $options): int
    {
        if (isset($options['help']) || [] === \array_diff_key($options, ['dry-run' => true])) {
            \fwrite($this->out, self::USAGE);

            return isset($options['help']) ? 0 : 1;
        }

        $json = (string) \file_get_contents($this->root . '/identity.json');
        $identity = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        $from = RenameSpec::fromIdentity(\is_array($identity) ? $identity : []);
        $to = new RenameSpec(
            self::stringOption($options, 'name') ?? $from->name,
            self::stringOption($options, 'slug') ?? $from->slug,
            self::stringOption($options, 'namespace') ?? $from->namespace,
            self::stringOption($options, 'prefix') ?? $from->prefix,
        );

        $shell = new Shell($this->root);
        $dryRun = isset($options['dry-run']);
        // `git checkout . && git clean -fd` is the way back from a failed rename.
        if (!$dryRun && !$shell->isClean()) {
            throw new RenameException('The working tree has uncommitted changes. Commit or stash them first.');
        }
        $renamer = new Renamer($this->root, $shell->gitFiles());
        $plan = $renamer->plan($from, $to);

        foreach (\array_keys($plan->writes) as $path) {
            \fwrite($this->out, "  edit  $path\n");
        }
        foreach ($plan->moves as $path => $newPath) {
            \fwrite($this->out, "  move  $path -> $newPath\n");
        }
        \fwrite($this->out, \sprintf("%d files to edit, %d to move.\n", \count($plan->writes), \count($plan->moves)));
        if ($dryRun) {
            \fwrite($this->out, "Dry run: nothing was changed.\n");

            return 0;
        }

        // apply() rewrites this tool's own files too: autoload now what may be
        // needed afterwards, or a later failure ends in "class not found".
        \class_exists(RenameException::class);
        $renamer->apply($plan);
        \fwrite($this->out, "Renamed. Updating Composer's autoloader and lock hash…\n");
        // The package name is part of the lock file's content hash.
        if (0 !== $shell->composer(['update', '--lock', '--no-install', '--no-interaction', '--quiet'])) {
            \fwrite($this->err, "composer update --lock failed; run it yourself.\n");

            return 1;
        }
        if (0 !== $shell->composer(['dump-autoload', '--quiet'])) {
            \fwrite($this->err, "composer dump-autoload failed; run it yourself.\n");

            return 1;
        }

        $leftovers = $renamer->leftovers($from, $to, $shell->gitFiles());
        if ([] !== $leftovers) {
            \fwrite($this->err, "Old tokens are still present:\n  " . \implode("\n  ", $leftovers) . "\n");

            return 1;
        }
        \fwrite($this->out, "Done. No old token is left. Run `composer check`.\n");

        return 0;
    }

    /**
     * @param list<string> $args
     * @return array<string, string|true>
     */
    private static function parse(array $args): array
    {
        $options = [];
        foreach ($args as $arg) {
            if (1 !== \preg_match('/^--(name|slug|namespace|prefix|dry-run|help)(?:=(.*))?$/s', $arg, $m)) {
                throw new RenameException('Unknown argument: ' . $arg . "\n\n" . self::USAGE);
            }
            $options[$m[1]] = $m[2] ?? true;
        }

        return $options;
    }

    /**
     * @param array<string, string|true> $options
     */
    private static function stringOption(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;
        if (true === $value) {
            throw new RenameException(\sprintf('--%s needs a value: --%s=…', $key, $key));
        }

        return $value;
    }
}
