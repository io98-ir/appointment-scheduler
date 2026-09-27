<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Customers\Application\CustomerReader;
use Vaqtyar\Modules\Customers\Application\CustomerService;
use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Modules\Customers\Contracts\CustomerDirectory;
use Vaqtyar\Modules\Customers\Domain\CustomerRepository;
use Vaqtyar\Modules\Customers\Infrastructure\Migrations\CreateCustomersTable;
use Vaqtyar\Modules\Customers\Infrastructure\Persistence\WpdbCustomerRepository;
use Vaqtyar\Modules\Customers\Infrastructure\Query\WpdbCustomerDirectory;
use Vaqtyar\Modules\Customers\Presentation\Rest\CustomerRoutes;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\WpAuthorizer;

/**
 * The people who book (architecture §3): the admin REST API over them, and
 * CustomerApi for the other modules. OTP login and the customer panel come
 * in T4.3 and T4.4.
 */
final class CustomersModule implements Module
{
    public function id(): string
    {
        return 'customers';
    }

    public function register(Container $container): void
    {
        $container->singleton(
            CustomerRepository::class,
            static fn (Container $c) => new WpdbCustomerRepository($c->get(Db::class), $c->get(Clock::class))
        );
        $container->singleton(CustomerService::class, static fn (Container $c) => new CustomerService(
            new WpAuthorizer(),
            $c->get(CustomerRepository::class)
        ));
        $container->singleton(
            CustomerApi::class,
            static fn (Container $c) => new CustomerReader($c->get(CustomerRepository::class))
        );
        $container->singleton(
            CustomerDirectory::class,
            static fn (Container $c) => new WpdbCustomerDirectory($c->get(Db::class))
        );
    }

    /**
     * @return list<\Vaqtyar\Kernel\Database\Migration>
     */
    public function migrations(): array
    {
        return [new CreateCustomersTable()];
    }

    /**
     * @return array<string, list<string>>
     */
    public function capabilities(): array
    {
        return [CustomerService::CAPABILITY => ['administrator']];
    }

    public function boot(Context $context): void
    {
        $container = $context->container;
        \add_action('rest_api_init', static function () use ($container): void {
            (new CustomerRoutes(
                $container->get(Router::class),
                $container->get(CustomerService::class)
            ))->register();
        });
        // The account is gone; the customer and their appointments stay.
        \add_action('deleted_user', static function (mixed $userId) use ($container): void {
            if (\is_numeric($userId)) {
                $container->get(CustomerRepository::class)->unlinkWpUser((int) $userId);
            }
        });
    }
}
