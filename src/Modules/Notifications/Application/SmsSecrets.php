<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

/**
 * The providers' keys and passwords. They are kept encrypted (or in
 * wp-config.php) and are never sent back to the screen: it learns only
 * whether one is set.
 */
interface SmsSecrets
{
    public function get(string $name): ?string;

    /**
     * An empty value removes it.
     */
    public function set(string $name, string $value): void;

    /**
     * A secret defined in wp-config.php cannot be changed here.
     */
    public function isFixed(string $name): bool;
}
