<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Field;

/**
 * The fields table's scope column (data-model §2): a field is either
 * global (every service shows it) or a specific service's own.
 */
enum FieldScope: string
{
    case Global = 'global';
    case Service = 'service';
}
