<?php

/**
 * Prints the translatable strings of the PHP sources as JSON, for
 * tools/i18n/build.mjs: [{msgid, msgctxt, plural, ref}].
 *
 *     php tools/i18n/extract.php
 *
 * Only calls whose text is a string literal count, as for wp i18n make-pot.
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

echo (static function (): string {
    $root = \dirname(__DIR__, 2);
    // function => argument positions of [msgid, plural, context]
    $functions = [
        '__' => [0, null, null],
        '_e' => [0, null, null],
        'esc_html__' => [0, null, null],
        'esc_attr__' => [0, null, null],
        'esc_html_e' => [0, null, null],
        'esc_attr_e' => [0, null, null],
        '_x' => [0, null, 1],
        '_ex' => [0, null, 1],
        'esc_html_x' => [0, null, 1],
        'esc_attr_x' => [0, null, 1],
        '_n' => [0, 1, null],
        '_nx' => [0, 1, 3],
    ];
    $literal = static function (mixed $arg): ?string {
        return $arg instanceof Node\Arg && $arg->value instanceof Node\Scalar\String_ ? $arg->value->value : null;
    };

    $paths = [$root . '/vaqtyar.php'];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));
    foreach ($files as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && 'php' === $file->getExtension()) {
            $paths[] = $file->getPathname();
        }
    }
    \sort($paths);

    $parser = (new ParserFactory())->createForHostVersion();
    $finder = new NodeFinder();
    $out = [];
    foreach ($paths as $path) {
        $ast = $parser->parse((string) \file_get_contents($path));
        if (null === $ast) {
            continue;
        }
        $relative = \str_replace('\\', '/', \substr($path, \strlen($root) + 1));
        foreach ($finder->findInstanceOf($ast, Node\Expr\FuncCall::class) as $call) {
            if (!$call->name instanceof Node\Name || !isset($functions[$call->name->getLast()])) {
                continue;
            }
            [$idPos, $pluralPos, $ctxPos] = $functions[$call->name->getLast()];
            $id = $literal($call->args[$idPos] ?? null);
            if (null === $id) {
                continue;
            }
            $out[] = [
                'msgid' => $id,
                'msgctxt' => null === $ctxPos ? null : $literal($call->args[$ctxPos] ?? null),
                'plural' => null === $pluralPos ? null : $literal($call->args[$pluralPos] ?? null),
                'ref' => $relative . ':' . $call->getStartLine(),
            ];
        }
    }

    return \json_encode($out, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
})();
