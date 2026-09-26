<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain;

/**
 * Where a holiday came from: the dataset shipped with the plugin, or the admin.
 */
enum HolidaySource: string
{
    case Dataset = 'dataset';
    case Manual = 'manual';
}
