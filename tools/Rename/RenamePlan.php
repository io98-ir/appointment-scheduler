<?php

declare(strict_types=1);

namespace Vaqtyar\Tools\Rename;

/**
 * What a rename will do. Paths are relative to the repository root, with "/".
 */
final class RenamePlan
{
    /**
     * @param array<string, string> $writes New content, keyed by the current path.
     * @param array<string, string> $moves  New path, keyed by the current path.
     */
    public function __construct(
        public readonly array $writes,
        public readonly array $moves,
    ) {
    }
}
