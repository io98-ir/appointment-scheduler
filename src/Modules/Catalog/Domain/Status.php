<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

/**
 * Whether customers can book a catalog item. An inactive one keeps its past
 * appointments and stays visible in the admin; deletion is a separate,
 * soft delete (data-model §1).
 */
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
