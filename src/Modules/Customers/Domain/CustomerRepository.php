<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Domain;

use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\PhoneNumber;

/**
 * The stored customers. A deleted customer is soft deleted, since
 * appointments keep pointing at them, and no method returns them.
 */
interface CustomerRepository
{
    public function find(int $id): ?Customer;

    public function findByPhone(PhoneNumber $phone): ?Customer;

    public function findByWpUser(int $wpUserId): ?Customer;

    /**
     * @param string $query Matches part of the name, the email or the phone
     *     number, after SearchText; "" matches everyone.
     * @return list<Customer> Newest first.
     */
    public function search(string $query, int $offset, int $limit): array;

    public function count(string $query): int;

    /**
     * Inserts when the id is null, with a new uuid, else updates the stored
     * row, which the caller has checked exists; the uuid never changes.
     *
     * @return Customer As stored, with its id and uuid.
     * @throws Conflict phone_taken when another customer has the number.
     * @throws NotFound customer_not_found when the customer was deleted meanwhile.
     */
    public function save(Customer $customer): Customer;

    /**
     * Also frees the phone number, so the person can sign up again.
     */
    public function delete(int $id): void;

    /**
     * Forgets a deleted WordPress account.
     */
    public function unlinkWpUser(int $wpUserId): void;
}
