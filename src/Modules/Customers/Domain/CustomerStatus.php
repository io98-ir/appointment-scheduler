<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Domain;

/**
 * A blocked customer stays on record, with their appointments, but cannot
 * book again.
 */
enum CustomerStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
}
