<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * Builds option names (ADR-000): {PREFIX}_{name}.
 */
final class Options
{
    /** Length of wp_options.option_name. */
    private const MAX_LENGTH = 191;

    public static function key(string $name): string
    {
        if (1 !== \preg_match('/^[a-z][a-z0-9_]*$/D', $name)) {
            throw KernelException::invalidName('option', $name, 'use a-z, 0-9 and _, starting with a letter');
        }

        $key = Identity::PREFIX . '_' . $name;
        if (\strlen($key) > self::MAX_LENGTH) {
            throw KernelException::nameTooLong('option', $key, self::MAX_LENGTH);
        }

        return $key;
    }
}
