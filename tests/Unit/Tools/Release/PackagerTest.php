<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Tools\Release;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Tools\Release\Packager;
use Vaqtyar\Tools\Release\ReleaseException;

final class PackagerTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = \sys_get_temp_dir() . '/packager-' . \bin2hex(\random_bytes(4));
        \mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->root . '/*') ?: [] as $file) {
            \unlink($file);
        }
        \rmdir($this->root);
    }

    public function testACleanZipHasNoProblems(): void
    {
        self::assertSame([], $this->packager()->verify(self::cleanEntries()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unwanted(): iterable
    {
        yield 'a test folder' => ['tests/Unit/FooTest.php', 'tests/Unit/FooTest.php is not part of a release.'];
        yield 'node modules' => ['node_modules/x/index.js', 'node_modules/x/index.js is not part of a release.'];
        yield 'the JS sources' => [
            'packages/admin/src/App.tsx',
            'packages/admin/src/App.tsx is not part of a release.',
        ];
        yield 'a git folder' => ['.git/HEAD', '.git/HEAD is not part of a release.'];
        yield 'a stray file at the top' => ['notes.txt', 'notes.txt is not part of a release.'];
        yield 'a markdown file among the sources' => [
            'src/Kernel/README.md',
            'src/Kernel/README.md is not a file src/ ships.',
        ];
        yield 'a dev dependency' => [
            'vendor/phpunit/phpunit/src/A.php',
            'vendor/phpunit/phpunit/src/A.php is a development dependency.',
        ];
        yield 'a dependency test folder' => [
            'vendor/acme/lib/tests/ATest.php',
            "vendor/acme/lib/tests/ATest.php is a dependency's own tests or docs.",
        ];
    }

    /**
     * @dataProvider unwanted
     */
    public function testRefusesWhatIsNotThePlugin(string $entry, string $problem): void
    {
        self::assertContains($problem, $this->packager()->verify([...self::cleanEntries(), $entry]));
    }

    public function testRefusesAZipWithoutTheBuildOrTheMainFile(): void
    {
        $entries = \array_values(\array_filter(
            self::cleanEntries(),
            static fn (string $entry): bool => 'build/admin.js' !== $entry && 'zetatool.php' !== $entry
        ));

        $problems = $this->packager()->verify($entries);

        self::assertContains('build/admin.js is missing: run pnpm build.', $problems);
        self::assertContains('zetatool.php is missing.', $problems);
    }

    public function testKeepsALicenseInsideVendorButNotItsTests(): void
    {
        self::assertTrue(Packager::keepInVendor('acme/lib/LICENSE.txt'));
        self::assertTrue(Packager::keepInVendor('acme/lib/src/Testing.php'));
        self::assertFalse(Packager::keepInVendor('acme/lib/Tests/A.php'));
        self::assertFalse(Packager::keepInVendor('acme/lib/docs/guide.md'));
        self::assertFalse(Packager::keepInVendor('acme/lib/.github/workflows/ci.yml'));
    }

    public function testTheVersionMustBeTheSameEverywhere(): void
    {
        $this->write('2.3.4', '2.3.4', '2.3.4', '2.3.4');
        self::assertSame([], $this->packager()->versionProblems());
        self::assertSame('2.3.4', $this->packager()->version());

        $this->write('2.3.4', '2.3.3', '2.3.2', '2.3.1');
        self::assertSame(
            [
                'The ZETATOOL_VERSION constant (2.3.3) is not the header\'s 2.3.4.',
                'readme.txt says Stable tag 2.3.2, the plugin is 2.3.4.',
                'CHANGELOG.md has no entry for 2.3.4.',
            ],
            $this->packager()->versionProblems()
        );
    }

    public function testACorruptPluginHeaderIsAnError(): void
    {
        \file_put_contents($this->root . '/zetatool.php', "<?php\n// no header\n");
        \file_put_contents($this->root . '/readme.txt', '');
        \file_put_contents($this->root . '/CHANGELOG.md', '');

        self::assertSame(['zetatool.php has no Version header.'], $this->packager()->versionProblems());
        $this->expectException(ReleaseException::class);
        $this->packager()->version();
    }

    /**
     * @return list<string>
     */
    private static function cleanEntries(): array
    {
        return [
            'zetatool.php',
            'uninstall.php',
            'readme.txt',
            'license.txt',
            'CHANGELOG.md',
            'user-guide-fa.md',
            'src/Kernel/Plugin.php',
            'build/admin.js',
            'build/admin.asset.php',
            'build/widget.js',
            'build/widget.asset.php',
            'build/admin.css',
            'assets/blocks.js',
            'assets/holidays/1405.json',
            'languages/zetatool-fa_IR.mo',
            'vendor/autoload.php',
            'vendor/acme/lib/src/Lib.php',
            'vendor/acme/lib/LICENSE',
        ];
    }

    private function packager(): Packager
    {
        return new Packager($this->root, 'zetatool', 'ZETATOOL');
    }

    private function write(string $header, string $constant, string $stable, string $changelog): void
    {
        $main = "<?php\n/**\n * Plugin Name: Zeta\n * Version:           $header\n */\n"
            . "define('ZETATOOL_VERSION', '$constant');\n";
        \file_put_contents($this->root . '/zetatool.php', $main);
        \file_put_contents($this->root . '/readme.txt', "=== Zeta ===\nStable tag: $stable\n");
        \file_put_contents($this->root . '/CHANGELOG.md', "# Changelog\n\n## [$changelog] - 2026-01-01\n");
    }
}
