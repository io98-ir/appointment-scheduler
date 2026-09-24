<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * Builds capability names (ADR-000): {PREFIX}_{name}, e.g. Caps::name('manage_bookings').
 */
final class Caps
{
    public static function name(string $name): string
    {
        if (1 !== \preg_match('/^[a-z][a-z0-9_]*$/D', $name)) {
            throw KernelException::invalidName('capability', $name, 'use a-z, 0-9 and _, starting with a letter');
        }

        return Identity::PREFIX . '_' . $name;
    }
}
