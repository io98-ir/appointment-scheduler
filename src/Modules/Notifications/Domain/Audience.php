<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Domain;

/**
 * Who a template writes to: the customer of the appointment, the staff
 * member who serves it, or the site's admin address.
 */
enum Audience: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Admin = 'admin';
}
