<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Customers\Application;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Customers\Application\CustomerReader;
use Vaqtyar\Modules\Customers\Application\CustomerService;
use Vaqtyar\Modules\Customers\Application\PhoneSessions;
use Vaqtyar\Modules\Customers\Domain\Customer;
use Vaqtyar\Modules\Customers\Domain\CustomerRepository;
use Vaqtyar\Modules\Customers\Domain\CustomerStatus;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\PhoneNumber;
use Vaqtyar\Shared\SystemClock;

/**
 * The use cases behind the admin customer API: authorization, existence and
 * the one-customer-per-phone and per-account rules. Storage is the
 * repository's (the integration suite).
 */
final class CustomerServiceTest extends TestCase
{
    public bool $allowed = true;

    /** @var array<int, Customer> */
    public array $stored = [];

    public ?string $searched = null;

    private CustomerService $service;

    private CustomerRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new class ($this) implements CustomerRepository {
            public function __construct(private readonly CustomerServiceTest $test)
            {
            }

            public function find(int $id): ?Customer
            {
                return $this->test->stored[$id] ?? null;
            }

            public function findByPhone(PhoneNumber $phone): ?Customer
            {
                foreach ($this->test->stored as $customer) {
                    if ($customer->phone->equals($phone)) {
                        return $customer;
                    }
                }

                return null;
            }

            public function findByWpUser(int $wpUserId): ?Customer
            {
                foreach ($this->test->stored as $customer) {
                    if ($wpUserId === $customer->wpUserId) {
                        return $customer;
                    }
                }

                return null;
            }

            /**
             * @return list<Customer>
             */
            public function search(string $query, ?CustomerStatus $status, int $offset, int $limit): array
            {
                $this->test->searched = $query;
                $matching = null === $status
                    ? $this->test->stored
                    : \array_filter($this->test->stored, static fn (Customer $c): bool => $c->status === $status);

                return \array_slice(\array_values($matching), $offset, $limit);
            }

            public function count(string $query, ?CustomerStatus $status): int
            {
                return null === $status
                    ? \count($this->test->stored)
                    : \count(\array_filter(
                        $this->test->stored,
                        static fn (Customer $c): bool => $c->status === $status
                    ));
            }

            public function save(Customer $customer): Customer
            {
                $id = $customer->id ?? \count($this->test->stored) + 1;
                $saved = new Customer(
                    $id,
                    null,
                    $customer->firstName,
                    $customer->lastName,
                    $customer->phone,
                    $customer->email,
                    $customer->wpUserId,
                    status: $customer->status
                );
                $this->test->stored[$id] = $saved;

                return $saved;
            }

            public function delete(int $id): void
            {
                unset($this->test->stored[$id]);
            }

            public function unlinkWpUser(int $wpUserId): void
            {
            }
        };
        $authorizer = new class ($this) implements Authorizer {
            public function __construct(private readonly CustomerServiceTest $test)
            {
            }

            public function allows(string $capability): bool
            {
                return $this->test->allowed && 'manage_customers' === $capability;
            }
        };
        $this->service = new CustomerService($authorizer, $this->repository);
    }

    public function testANewCustomerIsSaved(): void
    {
        $saved = $this->service->save(self::customer(null, '09121234567'));

        self::assertSame(1, $saved->id);
        self::assertSame('+989121234567', $this->service->customer(1)->phone->e164);
    }

    public function testAPhoneNumberBelongsToOneCustomer(): void
    {
        $this->service->save(self::customer(null, '09121234567'));

        $this->assertConflict('phone_taken', self::customer(null, '+98 912 123 4567'));
    }

    public function testACustomerKeepsTheirOwnNumberOnUpdate(): void
    {
        $this->service->save(self::customer(null, '09121234567'));

        $updated = $this->service->save(self::customer(1, '09121234567', 'Reza'));

        self::assertSame('Reza', $updated->firstName);
    }

    public function testAWordPressAccountBelongsToOneCustomer(): void
    {
        $this->service->save(self::customer(null, '09121234567', wpUserId: 5));

        $this->assertConflict('user_taken', self::customer(null, '09129999999', wpUserId: 5));
        self::assertSame(5, $this->service->save(self::customer(1, '09121234567', wpUserId: 5))->wpUserId);
    }

    public function testUpdatingOrDeletingAnUnknownCustomerIsNotFound(): void
    {
        foreach (
            [
            fn () => $this->service->save(self::customer(9, '09121234567')),
            fn () => $this->service->delete(9),
            fn () => $this->service->customer(9),
            ] as $call
        ) {
            try {
                $call();
                self::fail('No exception.');
            } catch (NotFound $e) {
                self::assertSame('customer_not_found', $e->errorCode);
            }
        }
    }

    public function testTheSearchIsNormalizedBeforeTheRepository(): void
    {
        $this->service->save(self::customer(null, '09121234567'));

        $page = $this->service->customers(" \u{0639}\u{0644}\u{064A}  ۰۹۱۲ ", null, 0, 20);

        self::assertSame('علی 0912', $this->searched);
        self::assertSame(1, $page->total);
        self::assertCount(1, $page->items);
    }

    public function testCustomersCanBeFilteredByStatus(): void
    {
        $this->service->save(self::customer(null, '09121234567'));
        $this->service->save(new Customer(
            null,
            null,
            'Sara',
            'Karimi',
            PhoneNumber::fromInput('09121234568'),
            status: CustomerStatus::Blocked
        ));

        $blocked = $this->service->customers('', CustomerStatus::Blocked, 0, 20);

        self::assertSame(1, $blocked->total);
        self::assertSame('Sara', $blocked->items[0]->firstName);
    }

    public function testEveryUseCaseNeedsTheCapability(): void
    {
        $this->allowed = false;
        foreach (
            [
            fn () => $this->service->save(self::customer(null, '09121234567')),
            fn () => $this->service->customers('', null, 0, 20),
            fn () => $this->service->customer(1),
            fn () => $this->service->delete(1),
            ] as $call
        ) {
            try {
                $call();
                self::fail('No exception.');
            } catch (Forbidden) {
            }
        }
        self::assertSame([], $this->stored);
    }

    public function testOtherModulesLearnWhetherACustomerCanBook(): void
    {
        $this->service->save(self::customer(null, '09121234567'));
        $this->service->save(
            new Customer(null, null, 'Ali', '', PhoneNumber::fromInput('09122222222'), status: CustomerStatus::Blocked)
        );
        $reader = new CustomerReader(
            $this->repository,
            new PhoneSessions('key'),
            new SystemClock(),
            static fn (): bool => false
        );

        self::assertSame([true, false, false], [$reader->canBook(1), $reader->canBook(2), $reader->canBook(3)]);
    }

    private function assertConflict(string $code, Customer $customer): void
    {
        try {
            $this->service->save($customer);
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame($code, $e->errorCode);
        }
    }

    private static function customer(?int $id, string $phone, string $first = 'Ali', ?int $wpUserId = null): Customer
    {
        return new Customer($id, null, $first, 'Karimi', PhoneNumber::fromInput($phone), wpUserId: $wpUserId);
    }
}
