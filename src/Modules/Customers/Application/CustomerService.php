<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Application;

use Vaqtyar\Modules\Customers\Domain\Customer;
use Vaqtyar\Modules\Customers\Domain\CustomerRepository;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\Page;
use Vaqtyar\Shared\Domain\SearchText;

/**
 * The admin's customer use cases. Each checks the capability again, after
 * the REST permission callback (architecture §12).
 *
 * The phone and account checks here give a clear error; the unique index
 * on the phone decides a race (CustomerRepository::save()). Two customers
 * saved at once with one account both pass: the account then reads as
 * the first of them (findByWpUser()).
 */
final class CustomerService
{
    public const CAPABILITY = 'manage_customers';

    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly CustomerRepository $customers,
    ) {
    }

    public function customer(int $id): Customer
    {
        $this->authorize();

        return $this->customers->find($id) ?? throw self::notFound();
    }

    /**
     * @param string $query as typed; normalized here (SearchText).
     * @return Page<Customer>
     */
    public function customers(string $query, int $offset, int $limit): Page
    {
        $this->authorize();
        $query = SearchText::normalize($query);

        return new Page($this->customers->search($query, $offset, $limit), $this->customers->count($query));
    }

    /**
     * A stored customer keeps their uuid, whatever the request says.
     *
     * @throws Conflict phone_taken or user_taken when another customer has the number or the account.
     */
    public function save(Customer $customer): Customer
    {
        $this->authorize();
        if (null !== $customer->id) {
            $this->customers->find($customer->id) ?? throw self::notFound();
        }
        $samePhone = $this->customers->findByPhone($customer->phone);
        if (null !== $samePhone && $samePhone->id !== $customer->id) {
            throw new Conflict('phone_taken', 'Another customer has this phone number.');
        }
        if (null !== $customer->wpUserId) {
            $sameUser = $this->customers->findByWpUser($customer->wpUserId);
            if (null !== $sameUser && $sameUser->id !== $customer->id) {
                throw new Conflict('user_taken', 'Another customer has this WordPress account.');
            }
        }

        return $this->customers->save($customer);
    }

    public function delete(int $id): void
    {
        $this->customer($id);
        $this->customers->delete($id);
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }

    private static function notFound(): NotFound
    {
        return new NotFound('customer_not_found', 'No customer has this id.');
    }
}
