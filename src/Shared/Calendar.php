<?php

declare(strict_types=1);

namespace Vaqtyar\Shared;

/**
 * The calendar dates are shown in. Storage is always Gregorian UTC.
 */
enum Calendar: string
{
    case Jalali = 'jalali';
    case Gregorian = 'gregorian';
}
