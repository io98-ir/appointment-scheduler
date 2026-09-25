<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * Builds action and filter names (ADR-000, architecture §10):
 * {HOOK_PREFIX}/{module}/{event}, e.g. Hooks::name('booking/appointment_confirmed').
 */
final class Hooks
{
    /**
     * @return non-empty-string
     */
    public static function name(string $name): string
    {
        if (1 !== \preg_match('#^[a-z][a-z0-9_]*(/[a-z][a-z0-9_]*)*$#D', $name)) {
            throw KernelException::invalidName('hook', $name, 'use a-z, 0-9 and _ segments separated by /');
        }

        return Identity::HOOK_PREFIX . '/' . $name;
    }
}
