<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

/**
 * The order of the admin list. Ties go by id, so pages never overlap.
 */
enum AppointmentSort
{
    case StartAsc;
    case StartDesc;
    case CreatedAsc;
    case CreatedDesc;
}
