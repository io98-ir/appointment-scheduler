<?php

declare(strict_types=1);

namespace Vaqtyar\Shared;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Shared\Domain\Authorizer;

/**
 * Asks WordPress about the current user. A guest holds no capability.
 */
final class WpAuthorizer implements Authorizer
{
    public function allows(string $capability): bool
    {
        return \current_user_can(Caps::name($capability));
    }
}
