<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain;

/**
 * Whose calendar a rule or an exception belongs to. A location's hours bound
 * the hours of the staff and resources at it.
 */
enum OwnerType: string
{
    case Staff = 'staff';
    case Resource = 'resource';
    case Location = 'location';
}
