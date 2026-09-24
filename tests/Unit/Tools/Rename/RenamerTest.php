<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Tools\Rename;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Tools\Rename\RenameException;
use Vaqtyar\Tools\Rename\Renamer;
use Vaqtyar\Tools\Rename\RenameSpec;

/**
 * Runs the renamer on a small fake plugin in a temporary directory. The tokens
 * are made up (not the repo's own identity) so this test stays meaningful
 * after the repo itself is renamed.
 */
final class RenamerTest extends TestCase
{
    private string $root;
    private RenameSpec $from;
    private RenameSpec $to;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = \sys_get_temp_dir() . '/rename-test-' . \bin2hex(\random_bytes(6));
        \mkdir($this->root);
        $this->from = new RenameSpec('Acme Book', 'acmebook', 'AcmeBook', 'acb');
        $this->to = new RenameSpec('Zeta Tool', 'zetatool', 'ZetaTool', 'ztx');

        $this->write('identity.json', "{\n\t\"old\": true\n}\n");
        $this->write('acmebook.php', <<<'PHP'
            <?php
            /**
             * Plugin Name:       Acme Book
             * Text Domain:       acmebook
             */
            define('ACMEBOOK_VERSION', '1.0.0');
            \AcmeBook\Kernel\Plugin::boot();
            PHP);
        $this->write('src/Kernel/Identity.php', <<<'PHP'
            <?php
            namespace AcmeBook\Kernel;
            final class Identity
            {
                public const NAME = 'Acme Book';
                public const SLUG = 'acmebook';
                public const PREFIX = 'acb';
                public const REST_NAMESPACE = 'acmebook/v1';
            }
            PHP);
        $this->write('tools/deptrac.yaml', "value: '#^AcmeBook\\\\Kernel\\\\#'\n");
        $this->write('languages/acmebook-fa_IR.po', "msgid \"\"\n");
        $this->write('assets/logo.png', "\x89PNG\0acmebook");
        $this->write('docs/notes.md', "The working name is acmebook.\n");
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->root);
        parent::tearDown();
    }

    public function testRenamesTokensCasePreservingAndRewritesTheIdentity(): void
    {
        $renamer = $this->renamer();
        $renamer->apply($renamer->plan($this->from, $this->to));

        self::assertSame(
            \json_encode($this->to->identity(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            \str_replace("\t", '    ', \rtrim($this->read('identity.json')))
        );
        self::assertStringStartsWith("{\n\t\"name\"", $this->read('identity.json'), 'Tab indentation (.editorconfig).');

        $main = $this->read('zetatool.php');
        self::assertStringContainsString(' * Plugin Name:       Zeta Tool', $main);
        self::assertStringContainsString(' * Text Domain:       zetatool', $main);
        self::assertStringContainsString("define('ZETATOOL_VERSION'", $main);
        self::assertStringContainsString('\ZetaTool\Kernel\Plugin::boot();', $main);
        self::assertFileDoesNotExist($this->root . '/acmebook.php');

        $identity = $this->read('src/Kernel/Identity.php');
        self::assertStringContainsString('namespace ZetaTool\Kernel;', $identity);
        self::assertStringContainsString("public const NAME = 'Zeta Tool';", $identity);
        self::assertStringContainsString("public const SLUG = 'zetatool';", $identity);
        self::assertStringContainsString("public const PREFIX = 'ztx';", $identity);
        self::assertStringContainsString("public const REST_NAMESPACE = 'zetatool/v1';", $identity);

        self::assertSame("value: '#^ZetaTool\\\\Kernel\\\\#'\n", $this->read('tools/deptrac.yaml'));
        self::assertFileExists($this->root . '/languages/zetatool-fa_IR.po');
        self::assertFileDoesNotExist($this->root . '/languages/acmebook-fa_IR.po');

        self::assertSame([], $renamer->leftovers($this->from, $this->to, self::listFiles($this->root)));
    }

    public function testLeavesBinaryAndExcludedFilesAlone(): void
    {
        $renamer = $this->renamer();
        $renamer->apply($renamer->plan($this->from, $this->to));

        self::assertSame("\x89PNG\0acmebook", $this->read('assets/logo.png'));
        self::assertSame("The working name is acmebook.\n", $this->read('docs/notes.md'));
    }

    public function testDryRunPlanChangesNothingOnDisk(): void
    {
        $plan = $this->renamer()->plan($this->from, $this->to);

        self::assertContains('src/Kernel/Identity.php', \array_keys($plan->writes));
        self::assertSame(
            ['acmebook.php' => 'zetatool.php', 'languages/acmebook-fa_IR.po' => 'languages/zetatool-fa_IR.po'],
            $plan->moves
        );
        self::assertFileExists($this->root . '/acmebook.php');
        self::assertStringContainsString('AcmeBook', $this->read('src/Kernel/Identity.php'));
    }

    public function testLeftoversReportsEveryCaseOfAnOldToken(): void
    {
        $this->write('src/Stray.php', "<?php\n// AcMeBoOk\n// ACB_FLAG\n");

        self::assertSame(
            ['src/Stray.php:2: acmebook', 'src/Stray.php:3: acb'],
            $this->renamer()->leftovers($this->from, $this->to, ['src/Stray.php'])
        );
    }

    public function testRefusesANewTokenThatAlreadyAppearsInTheCode(): void
    {
        // "ztx" already used for something else: a later rename could not tell them apart.
        $this->write('src/Other.php', "<?php\n// ztx_legacy\n");

        $this->expectException(RenameException::class);
        $this->expectExceptionMessage('"ztx" already appears in src/Other.php');

        $this->renamer()->plan($this->from, $this->to);
    }

    public function testNewTokensThatOnlyAppearInsideOldOnesAreFine(): void
    {
        // "acme" occurs in the code only as part of "acmebook", which is being replaced.
        $to = new RenameSpec('Acme', 'acme', 'Acme', 'bkx');

        $plan = $this->renamer()->plan($this->from, $to);

        self::assertSame('acme.php', $plan->moves['acmebook.php'] ?? null);
    }

    public function testRefusesANewTokenThatContainsAnOldOne(): void
    {
        // The leftover check could never pass: "acmebookpro" contains "acmebook".
        $to = new RenameSpec('Acme Pro', 'acmebookpro', 'AcmeBookPro', 'bkx');

        $this->expectException(RenameException::class);
        $this->expectExceptionMessage('contains the old token "acmebook"');

        $this->renamer()->plan($this->from, $to);
    }

    public function testChangingOnlyTheNameTouchesOnlyTheNamePlaces(): void
    {
        $to = new RenameSpec('Acme Booking Pro', 'acmebook', 'AcmeBook', 'acb');
        $renamer = $this->renamer();
        $plan = $renamer->plan($this->from, $to);

        self::assertSame(['identity.json', 'src/Kernel/Identity.php', 'acmebook.php'], \array_keys($plan->writes));
        self::assertSame([], $plan->moves);

        $renamer->apply($plan);
        self::assertStringContainsString(' * Plugin Name:       Acme Booking Pro', $this->read('acmebook.php'));
        self::assertSame([], $renamer->leftovers($this->from, $to, self::listFiles($this->root)));
    }

    public function testANameWithDollarSignsIsWrittenVerbatim(): void
    {
        $to = new RenameSpec('Top$1 ${2} Pro', 'zetatool', 'ZetaTool', 'ztx');
        $renamer = $this->renamer();
        $renamer->apply($renamer->plan($this->from, $to));

        $identity = $this->read('src/Kernel/Identity.php');
        self::assertStringContainsString("public const NAME = 'Top\$1 \${2} Pro';", $identity);
        self::assertStringContainsString(' * Plugin Name:       Top$1 ${2} Pro' . "\n", $this->read('zetatool.php'));
    }

    public function testRefusesBeforeWritingWhenAnOldTokenWouldRemain(): void
    {
        // Keeping the old name "AcmeBook" while the slug changes would leave "acmebook" behind.
        $to = new RenameSpec('AcmeBook', 'zetatool', 'ZetaTool', 'ztx');

        try {
            $this->renamer()->plan($this->from, $to);
            self::fail('The plan should have been refused.');
        } catch (RenameException $e) {
            self::assertStringContainsString('identity.json:2: acmebook', $e->getMessage());
            self::assertStringContainsString('pass --name', $e->getMessage());
        }
        self::assertFileExists($this->root . '/acmebook.php');
    }

    public function testNeverRewritesPartOfAnotherWord(): void
    {
        // "acb" inside a hash or word is not our prefix: refuse rather than corrupt it.
        $this->write('src/Hash.php', "<?php\n// sha512-Xacb9Q==\n");

        $this->expectException(RenameException::class);
        $this->expectExceptionMessage('src/Hash.php:2: acb');

        $this->renamer()->plan($this->from, $this->to);
    }

    public function testLockFilesAreLeftToTheirTools(): void
    {
        $this->write('pnpm-lock.yaml', "integrity: sha512-acbXYZacmebook==\n");
        $renamer = $this->renamer();
        $renamer->apply($renamer->plan($this->from, $this->to));

        self::assertSame("integrity: sha512-acbXYZacmebook==\n", $this->read('pnpm-lock.yaml'));
    }

    public function testRefusesToRenameToTheSameIdentity(): void
    {
        $this->expectException(RenameException::class);

        $this->renamer()->plan($this->from, $this->from);
    }

    public function testFailsWhenAStructuredEditFindsNothingToChange(): void
    {
        $this->write('src/Kernel/Identity.php', "<?php\nnamespace AcmeBook\\Kernel;\n");

        $this->expectException(RenameException::class);
        $this->expectExceptionMessage('src/Kernel/Identity.php');

        $this->renamer()->plan($this->from, $this->to);
    }

    private function renamer(): Renamer
    {
        return new Renamer($this->root, self::listFiles($this->root));
    }

    private function write(string $path, string $content): void
    {
        $full = $this->root . '/' . $path;
        if (!\is_dir(\dirname($full))) {
            \mkdir(\dirname($full), 0777, true);
        }
        \file_put_contents($full, $content);
    }

    private function read(string $path): string
    {
        $content = \file_get_contents($this->root . '/' . $path);
        self::assertIsString($content);

        return $content;
    }

    /**
     * @return list<string>
     */
    private static function listFiles(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            $files[] = \str_replace('\\', '/', \substr($file->getPathname(), \strlen($root) + 1));
        }
        \sort($files);

        return $files;
    }

    private static function removeDirectory(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
        }
        \rmdir($dir);
    }
}
