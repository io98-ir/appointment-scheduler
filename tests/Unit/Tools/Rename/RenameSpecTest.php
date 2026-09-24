<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Tools\Rename;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Tools\Rename\RenameException;
use Vaqtyar\Tools\Rename\RenameSpec;

final class RenameSpecTest extends TestCase
{
    public function testDerivesTheOtherIdentifiersFromTheSlug(): void
    {
        $spec = new RenameSpec('Zeta Tool', 'zetatool', 'ZetaTool', 'ztx');

        self::assertSame(
            [
                'name' => 'Zeta Tool',
                'slug' => 'zetatool',
                'namespace' => 'ZetaTool',
                'const_prefix' => 'ZETATOOL',
                'prefix' => 'ztx',
                'hook_prefix' => 'zetatool',
                'text_domain' => 'zetatool',
                'rest_namespace' => 'zetatool/v1',
            ],
            $spec->identity()
        );
    }

    public function testAcceptsANonLatinName(): void
    {
        self::assertSame('وقت‌یار', (new RenameSpec('وقت‌یار', 'zetatool', 'ZetaTool', 'ztx'))->name);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function invalidSpecs(): iterable
    {
        yield 'empty name' => ['', 'zetatool', 'ZetaTool', 'ztx'];
        yield 'quote in name' => ["Zeta's", 'zetatool', 'ZetaTool', 'ztx'];
        yield 'backslash in name' => ['Zeta\\Tool', 'zetatool', 'ZetaTool', 'ztx'];
        yield 'newline in name' => ["Zeta\nTool", 'zetatool', 'ZetaTool', 'ztx'];
        yield 'comment end in name' => ['Zeta */ Tool', 'zetatool', 'ZetaTool', 'ztx'];
        yield 'hyphen in slug' => ['Zeta', 'zeta-tool', 'ZetaTool', 'ztx'];
        yield 'uppercase slug' => ['Zeta', 'ZetaTool', 'ZetaTool', 'ztx'];
        yield 'short slug' => ['Zeta', 'zt', 'ZetaTool', 'ztx'];
        yield 'lowercase namespace' => ['Zeta', 'zetatool', 'zetaTool', 'ztx'];
        yield 'namespace with separator' => ['Zeta', 'zetatool', 'Zeta\\Tool', 'ztx'];
        yield 'namespace equal to the constant prefix' => ['Zeta', 'zetatool', 'ZETATOOL', 'ztx'];
        yield 'long prefix' => ['Zeta', 'zetatool', 'ZetaTool', 'ztxabcd'];
        yield 'short prefix' => ['Zeta', 'zetatool', 'ZetaTool', 'zx'];
        yield 'trailing newline in slug' => ['Zeta', "zetatool\n", 'ZetaTool', 'ztx'];
        yield 'prefix inside the slug' => ['Zeta', 'zetatool', 'ZetaTool', 'zeta'];
        yield 'slug inside the prefix' => ['Zeta', 'zet', 'Zet', 'zetax'];
    }

    /**
     * @dataProvider invalidSpecs
     */
    public function testRejectsInvalidIdentifiers(string $name, string $slug, string $namespace, string $prefix): void
    {
        $this->expectException(RenameException::class);

        new RenameSpec($name, $slug, $namespace, $prefix);
    }

    public function testReadsIdentityJson(): void
    {
        $spec = RenameSpec::fromIdentity((new RenameSpec('Acme Book', 'acmebook', 'AcmeBook', 'acb'))->identity());

        self::assertSame('Acme Book', $spec->name);
        self::assertSame('acmebook', $spec->slug);
        self::assertSame('AcmeBook', $spec->namespace);
        self::assertSame('acb', $spec->prefix);
    }

    public function testRejectsIdentityJsonThatDoesNotFollowTheDerivationRules(): void
    {
        $identity = (new RenameSpec('Acme Book', 'acmebook', 'AcmeBook', 'acb'))->identity();
        $identity['hook_prefix'] = 'acme_book';

        $this->expectException(RenameException::class);
        $this->expectExceptionMessage('"hook_prefix"');

        RenameSpec::fromIdentity($identity);
    }

    public function testRejectsIncompleteIdentityJson(): void
    {
        $this->expectException(RenameException::class);
        $this->expectExceptionMessage('"slug"');

        RenameSpec::fromIdentity(['name' => 'Acme Book']);
    }
}
