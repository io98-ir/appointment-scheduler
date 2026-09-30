<?php

declare(strict_types=1);

namespace Vaqtyar\Tools\Release;

use Vaqtyar\Tools\Rename\Shell;

/**
 * Builds the zip a customer installs (T6.6): only what runs, in a folder
 * named after the slug, with the production dependencies and the built
 * admin and widget. Everything else of the repository (tests, tools, docs,
 * JS sources, dev dependencies) stays out, and verify() refuses a zip that
 * has any of it, so a new top-level folder cannot ship by accident.
 *
 * The file and folder names come from identity.json, so a renamed plugin
 * builds the same way (ADR-000).
 */
final class Packager
{
    /** Folders shipped as they are, by the extensions a file in them may have. */
    private const FOLDERS = [
        'src' => ['php'],
        'build' => ['js', 'css', 'php'],
        'assets' => ['js', 'json', 'css'],
        'languages' => ['mo', 'json'],
    ];

    /** Files beside the main one that ship. */
    private const FILES = ['uninstall.php', 'readme.txt', 'license.txt', 'CHANGELOG.md', 'user-guide-fa.md'];

    /** Folders of a dependency that only its own developers need. */
    private const VENDOR_JUNK = ['tests', 'test', 'docs', '.github', '.git'];

    /**
     * Files of a dependency nobody reads at run time: its readme and change
     * list, and Composer's record of what is installed. Licenses stay.
     */
    private const VENDOR_JUNK_FILE = '/^(readme(\.\w+)?|changelog(\.\w+)?|installed\.(json|php))$|\.md$/i';

    /** Packages that are dev-only here; one of them in the zip means --no-dev was skipped. */
    private const DEV_PACKAGES = ['phpunit', 'squizlabs', 'phpstan', 'mockery', 'brain', 'deptrac', 'slevomat'];

    /** The bundles the plugin cannot start its screens without. */
    private const REQUIRED_BUILD = ['admin.js', 'admin.asset.php', 'widget.js', 'widget.asset.php'];

    public function __construct(
        private readonly string $root,
        private readonly string $slug,
        private readonly string $constPrefix,
    ) {
    }

    /**
     * @throws ReleaseException
     */
    public static function fromRoot(string $root): self
    {
        $json = \is_readable("$root/identity.json") ? \file_get_contents("$root/identity.json") : false;
        $identity = false === $json ? null : \json_decode($json, true);
        $slug = \is_array($identity) ? ($identity['slug'] ?? null) : null;
        $prefix = \is_array($identity) ? ($identity['const_prefix'] ?? null) : null;
        if (!\is_string($slug) || !\is_string($prefix)) {
            throw new ReleaseException('identity.json has no slug and const_prefix.');
        }

        return new self($root, $slug, $prefix);
    }

    public function slug(): string
    {
        return $this->slug;
    }

    /**
     * Whether a file inside vendor/ ships.
     */
    public static function keepInVendor(string $path): bool
    {
        $segments = \explode('/', $path);
        foreach ($segments as $segment) {
            if (\in_array(\strtolower($segment), self::VENDOR_JUNK, true)) {
                return false;
            }
        }

        return 1 !== \preg_match(self::VENDOR_JUNK_FILE, \end($segments));
    }

    /**
     * What is wrong with a zip's entries (paths below the plugin folder).
     *
     * @param list<string> $entries
     * @return list<string> Empty when the zip is clean.
     */
    public function verify(array $entries): array
    {
        $problems = [];
        $allowedTop = \array_merge(
            ["{$this->slug}.php", 'vendor'],
            self::FILES,
            \array_keys(self::FOLDERS)
        );
        foreach ($entries as $entry) {
            $segments = \explode('/', $entry);
            if (!\in_array($segments[0], $allowedTop, true)) {
                $problems[] = "$entry is not part of a release.";
                continue;
            }
            $folder = self::FOLDERS[$segments[0]] ?? null;
            if (null !== $folder && !\in_array(\strtolower(\pathinfo($entry, \PATHINFO_EXTENSION)), $folder, true)) {
                $problems[] = "$entry is not a file {$segments[0]}/ ships.";
            }
            if ('vendor' === $segments[0]) {
                if (\in_array($segments[1] ?? '', self::DEV_PACKAGES, true)) {
                    $problems[] = "$entry is a development dependency.";
                } elseif (!self::keepInVendor($entry)) {
                    $problems[] = "$entry is a dependency's own tests or docs.";
                }
            }
        }
        foreach (self::REQUIRED_BUILD as $file) {
            if (!\in_array("build/$file", $entries, true)) {
                $problems[] = "build/$file is missing: run pnpm build.";
            }
        }
        foreach (["{$this->slug}.php", 'uninstall.php', 'readme.txt', 'license.txt', 'vendor/autoload.php'] as $file) {
            if (!\in_array($file, $entries, true)) {
                $problems[] = "$file is missing.";
            }
        }

        return $problems;
    }

    /**
     * The version must be the same in the plugin header, its constant, the
     * readme's stable tag and the changelog, or an update would announce one
     * number and ship another.
     *
     * @return list<string>
     */
    public function versionProblems(): array
    {
        $main = $this->read("{$this->slug}.php");
        $readme = $this->read('readme.txt');
        $changelog = $this->read('CHANGELOG.md');
        $header = 1 === \preg_match('/^[ \t\/*#@]*Version:\s*(\S+)/mi', $main, $m) ? $m[1] : null;
        $constant = 1 === \preg_match(
            "/define\\(\\s*'{$this->constPrefix}_VERSION'\\s*,\\s*'([^']+)'/",
            $main,
            $m
        ) ? $m[1] : null;
        $stable = 1 === \preg_match('/^Stable tag:\s*(\S+)/mi', $readme, $m) ? $m[1] : null;
        if (null === $header) {
            return ["{$this->slug}.php has no Version header."];
        }

        $problems = [];
        if ($constant !== $header) {
            $problems[] = "The {$this->constPrefix}_VERSION constant ($constant) is not the header's $header.";
        }
        if ($stable !== $header) {
            $problems[] = "readme.txt says Stable tag $stable, the plugin is $header.";
        }
        if (1 !== \preg_match('/^## \[' . \preg_quote($header, '/') . '\]/m', $changelog)) {
            $problems[] = "CHANGELOG.md has no entry for $header.";
        }

        return $problems;
    }

    public function version(): string
    {
        return 1 === \preg_match('/^[ \t\/*#@]*Version:\s*(\S+)/mi', $this->read("{$this->slug}.php"), $m)
            ? $m[1]
            : throw new ReleaseException("{$this->slug}.php has no Version header.");
    }

    /**
     * Stages the plugin under $dist/{slug}/ and zips it to $dist/{slug}-{version}.zip.
     *
     * @return string The zip's path.
     * @throws ReleaseException
     */
    public function build(string $dist): string
    {
        $problems = $this->versionProblems();
        if ([] !== $problems) {
            throw new ReleaseException("The version is not the same everywhere:\n- " . \implode("\n- ", $problems));
        }
        $stage = "$dist/{$this->slug}";
        $this->removeTree($stage);
        foreach ($this->files() as $file) {
            $this->copy("{$this->root}/$file", "$stage/$file");
        }

        // Production dependencies only, installed where they will ship, so nothing of the dev set is ever there.
        $this->copy("{$this->root}/composer.json", "$stage/composer.json");
        $this->copy("{$this->root}/composer.lock", "$stage/composer.lock");
        $code = (new Shell($stage))->composer(
            ['install', '--no-dev', '--no-interaction', '--no-progress', '--classmap-authoritative', '--quiet']
        );
        if (0 !== $code) {
            throw new ReleaseException('composer install --no-dev failed.');
        }
        \unlink("$stage/composer.json");
        \unlink("$stage/composer.lock");
        $this->stripVendor("$stage/vendor");

        $entries = $this->listFiles($stage);
        $problems = $this->verify($entries);
        if ([] !== $problems) {
            throw new ReleaseException("The zip is not clean:\n- " . \implode("\n- ", \array_slice($problems, 0, 20)));
        }

        $zip = "$dist/{$this->slug}-{$this->version()}.zip";
        $this->zip($stage, $entries, $zip);
        return $zip;
    }

    /**
     * The files of our own code that ship, by relative path.
     *
     * @return list<string>
     * @throws ReleaseException
     */
    public function files(): array
    {
        $files = ["{$this->slug}.php", ...self::FILES];
        foreach (self::FOLDERS as $folder => $extensions) {
            if (!\is_dir("{$this->root}/$folder")) {
                throw new ReleaseException("$folder/ is missing" . ('build' === $folder ? ': run pnpm build.' : '.'));
            }
            foreach ($this->listFiles("{$this->root}/$folder") as $file) {
                if (\in_array(\strtolower(\pathinfo($file, \PATHINFO_EXTENSION)), $extensions, true)) {
                    $files[] = "$folder/$file";
                }
            }
        }
        foreach ($files as $file) {
            if (!\is_file("{$this->root}/$file")) {
                throw new ReleaseException("$file is missing.");
            }
        }

        return $files;
    }

    private function read(string $file): string
    {
        $content = \is_readable("{$this->root}/$file") ? \file_get_contents("{$this->root}/$file") : false;

        return false === $content ? throw new ReleaseException("$file cannot be read.") : $content;
    }

    /**
     * @return list<string> Files below $dir, relative, "/" separated, sorted.
     */
    private function listFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[] = \str_replace('\\', '/', \substr($file->getPathname(), \strlen($dir) + 1));
            }
        }
        \sort($files);

        return $files;
    }

    private function stripVendor(string $vendor): void
    {
        foreach ($this->listFiles($vendor) as $file) {
            if (!self::keepInVendor($file)) {
                \unlink("$vendor/$file");
            }
        }
        $this->removeEmptyDirs($vendor);
    }

    private function removeEmptyDirs(string $dir): void
    {
        foreach (\scandir($dir) ?: [] as $name) {
            if ('.' === $name || '..' === $name || !\is_dir("$dir/$name")) {
                continue;
            }
            $this->removeEmptyDirs("$dir/$name");
            if ([] === \array_diff(\scandir("$dir/$name") ?: [], ['.', '..'])) {
                \rmdir("$dir/$name");
            }
        }
    }

    private function copy(string $from, string $to): void
    {
        if (!\is_dir(\dirname($to)) && !\mkdir(\dirname($to), 0777, true)) {
            throw new ReleaseException('Cannot create ' . \dirname($to) . '.');
        }
        if (!\copy($from, $to)) {
            throw new ReleaseException("Cannot copy $from.");
        }
    }

    private function removeTree(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item instanceof \SplFileInfo) {
                $item->isDir() && !$item->isLink() ? \rmdir($item->getPathname()) : \unlink($item->getPathname());
            }
        }
        \rmdir($dir);
    }

    /**
     * @param list<string> $entries
     */
    private function zip(string $stage, array $entries, string $zipPath): void
    {
        if (\is_file($zipPath)) {
            \unlink($zipPath);
        }
        $zip = new \ZipArchive();
        if (true !== $zip->open($zipPath, \ZipArchive::CREATE)) {
            throw new ReleaseException("Cannot create $zipPath.");
        }
        foreach ($entries as $entry) {
            $name = "{$this->slug}/$entry";
            $zip->addFile("$stage/$entry", $name);
            // Smallest, not fastest: built once, downloaded many times.
            $zip->setCompressionName($name, \ZipArchive::CM_DEFLATE, 9);
        }
        if (!$zip->close()) {
            throw new ReleaseException("Cannot write $zipPath.");
        }
    }
}
