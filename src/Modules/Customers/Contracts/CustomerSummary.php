<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Contracts;

/**
 * A customer as a list of appointments shows them. A deleted customer is
 * still named, since their appointments stay; their phone is gone.
 */
final class CustomerSummary
{
    /**
     * @param ?string $phone E.164; null once deleted.
     * @param ?string $email as stored; callers must not use it for a deleted customer.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $phone,
        public readonly bool $deleted,
        public readonly ?string $email = null,
    ) {
    }
}
