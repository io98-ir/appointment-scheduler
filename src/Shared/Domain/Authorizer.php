<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * Whether the current user holds a capability. The port the Application
 * layer checks authorization through, since it cannot call WordPress
 * (architecture §2, §12); Shared\WpAuthorizer is the WordPress side.
 */
interface Authorizer
{
    /**
     * @param string $capability The short name, as for Caps::name(), e.g. "manage_catalog".
     */
    public function allows(string $capability): bool;
}
