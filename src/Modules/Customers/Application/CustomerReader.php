<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Application;

use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Modules\Customers\Domain\Customer;
use Vaqtyar\Modules\Customers\Domain\CustomerRepository;
use Vaqtyar\Shared\Domain\Email;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\PhoneNumber;

final class CustomerReader implements CustomerApi
{
    public function __construct(private readonly CustomerRepository $customers)
    {
    }

    public function canBook(int $customerId): bool
    {
        return $this->customers->find($customerId)?->canBook() ?? false;
    }

    public function forBooking(string $phone, string $firstName, string $lastName, ?string $email): int
    {
        $number = PhoneNumber::fromInput($phone);
        $existing = $this->customers->findByPhone($number);
        if (null !== $existing) {
            if (!$existing->canBook() || null === $existing->id) {
                throw new InvalidValue('customer_unavailable', 'The customer does not exist or is blocked.');
            }

            return $existing->id;
        }
        $saved = $this->customers->save(new Customer(
            null,
            null,
            \trim($firstName),
            \trim($lastName),
            $number,
            null === $email || '' === \trim($email) ? null : Email::fromInput($email)
        ));

        return $saved->id ?? throw new \LogicException('A saved customer has an id.');
    }
}
