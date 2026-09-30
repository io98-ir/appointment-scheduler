<?php

declare(strict_types=1);

namespace Vaqtyar\Tools\Release;

/**
 * A release that cannot be built, with the reason for whoever runs the tool.
 */
final class ReleaseException extends \RuntimeException
{
}
