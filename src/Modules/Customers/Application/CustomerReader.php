<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Application;

use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Modules\Customers\Domain\CustomerRepository;

final class CustomerReader implements CustomerApi
{
    public function __construct(private readonly CustomerRepository $customers)
    {
    }

    public function canBook(int $customerId): bool
    {
        return $this->customers->find($customerId)?->canBook() ?? false;
    }
}
