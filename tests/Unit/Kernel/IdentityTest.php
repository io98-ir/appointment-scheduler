<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Identity;

/**
 * identity.json is the source that tools/rename.php rewrites (ADR-000); these
 * tests keep the runtime constants and the literal-only places in step with it.
 */
final class IdentityTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function testConstantsMatchIdentityJson(): void
    {
        $json = self::identityJson();

        self::assertSame($json['name'], Identity::NAME);
        self::assertSame($json['slug'], Identity::SLUG);
        self::assertSame($json['prefix'], Identity::PREFIX);
        self::assertSame($json['hook_prefix'], Identity::HOOK_PREFIX);
        self::assertSame($json['rest_namespace'], Identity::REST_NAMESPACE);
    }

    public function testMainFileIsNamedAfterTheSlugAndDeclaresTheIdentity(): void
    {
        $json = self::identityJson();
        $mainFile = self::ROOT . '/' . $json['slug'] . '.php';

        self::assertFileExists($mainFile);
        $source = (string) \file_get_contents($mainFile);
        self::assertStringContainsString(' * Plugin Name:       ' . $json['name'] . "\n", $source);
        self::assertStringContainsString(' * Text Domain:       ' . $json['text_domain'] . "\n", $source);
        self::assertStringContainsString("define('" . $json['const_prefix'] . "_VERSION'", $source);
        self::assertStringContainsString("define('" . $json['const_prefix'] . "_FILE'", $source);
    }

    public function testComposerAutoloadUsesTheNamespace(): void
    {
        $autoload = self::decodeJsonFile('composer.json')['autoload'] ?? null;
        self::assertIsArray($autoload);
        $psr4 = $autoload['psr-4'] ?? null;
        self::assertIsArray($psr4);

        self::assertSame([self::identityJson()['namespace'] . '\\' => 'src/'], $psr4);
    }

    /**
     * @return array<string, string>
     */
    private static function identityJson(): array
    {
        $json = self::decodeJsonFile('identity.json');
        $keys = ['name', 'slug', 'namespace', 'const_prefix', 'prefix', 'hook_prefix', 'text_domain', 'rest_namespace'];
        foreach ($keys as $key) {
            self::assertIsString($json[$key] ?? null, "identity.json: missing \"$key\".");
        }

        /** @var array<string, string> $json */
        return $json;
    }

    /**
     * @return array<mixed>
     */
    private static function decodeJsonFile(string $file): array
    {
        $data = \json_decode((string) \file_get_contents(self::ROOT . '/' . $file), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
