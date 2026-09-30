<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Domain;

/**
 * The three levels WordPress Site Health uses, so a check maps to it as-is.
 */
enum HealthStatus: string
{
    case Good = 'good';
    case Recommended = 'recommended';
    case Critical = 'critical';
}
