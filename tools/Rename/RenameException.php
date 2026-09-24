<?php

declare(strict_types=1);

namespace Vaqtyar\Tools\Rename;

/**
 * A rename that cannot be done safely. Nothing has been written when it is thrown.
 */
final class RenameException extends \RuntimeException
{
}
