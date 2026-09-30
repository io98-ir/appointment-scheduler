<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Domain;

/**
 * One verdict. The id names the check and the status its outcome; the
 * Presentation layer turns the pair into text.
 */
final class HealthCheck
{
    public function __construct(public readonly string $id, public readonly HealthStatus $status)
    {
    }
}
