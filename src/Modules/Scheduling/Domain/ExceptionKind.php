<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain;

/**
 * What an exception does to one date (DayPlan): time off closes it, extra
 * hours open it, blocked time stays open but busy.
 */
enum ExceptionKind: string
{
    case Off = 'off';
    case Extra = 'extra';
    case Blocked = 'blocked';
}
