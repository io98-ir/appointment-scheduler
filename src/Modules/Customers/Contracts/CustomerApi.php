<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Contracts;

/**
 * What other modules may ask of Customers.
 */
interface CustomerApi
{
    /**
     * Whether the customer exists, is not deleted and is not blocked.
     */
    public function canBook(int $customerId): bool;
}
