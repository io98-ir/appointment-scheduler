<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Customers\Application\Captcha;
use Vaqtyar\Modules\Customers\Application\CustomerReader;
use Vaqtyar\Modules\Customers\Application\CustomerService;
use Vaqtyar\Modules\Customers\Application\OtpSender;
use Vaqtyar\Modules\Customers\Application\OtpService;
use Vaqtyar\Modules\Customers\Application\OtpStore;
use Vaqtyar\Modules\Customers\Application\PhoneSessions;
use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Modules\Customers\Contracts\CustomerDirectory;
use Vaqtyar\Modules\Customers\Domain\CustomerRepository;
use Vaqtyar\Modules\Customers\Infrastructure\HookOtpSender;
use Vaqtyar\Modules\Customers\Infrastructure\LoginSettings;
use Vaqtyar\Modules\Customers\Infrastructure\Migrations\CreateCustomersTable;
use Vaqtyar\Modules\Customers\Infrastructure\Migrations\CreateOtpCodesTable;
use Vaqtyar\Modules\Customers\Infrastructure\Persistence\WpdbCustomerRepository;
use Vaqtyar\Modules\Customers\Infrastructure\Persistence\WpdbOtpStore;
use Vaqtyar\Modules\Customers\Infrastructure\Query\WpdbCustomerDirectory;
use Vaqtyar\Modules\Customers\Presentation\Rest\CustomerRoutes;
use Vaqtyar\Modules\Customers\Presentation\Rest\OtpRoutes;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\WpAuthorizer;

/**
 * The people who book (architecture §3): the admin REST API over them,
 * CustomerApi for the other modules, and the phone login (one-time codes,
 * phone sessions and a captcha; T4.3). The customer panel comes in T4.4.
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
        $container->singleton(PhoneSessions::class, static fn () => new PhoneSessions(self::key()));
        $container->singleton(
            CustomerApi::class,
            static fn (Container $c) => new CustomerReader(
                $c->get(CustomerRepository::class),
                $c->get(PhoneSessions::class),
                $c->get(Clock::class),
                static fn (): bool => $c->get(Settings::class)->get(LoginSettings::class)->requirePhoneVerification
            )
        );
        $container->singleton(OtpStore::class, static fn (Container $c) => new WpdbOtpStore($c->get(Db::class)));
        $container->singleton(OtpSender::class, static fn () => new HookOtpSender());
        $container->singleton(OtpService::class, static fn (Container $c) => new OtpService(
            $c->get(OtpStore::class),
            $c->get(OtpSender::class),
            $c->get(PhoneSessions::class),
            $c->get(Clock::class),
            self::key()
        ));
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
        return [new CreateCustomersTable(), new CreateOtpCodesTable()];
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
            (new OtpRoutes(
                $container->get(Router::class),
                static fn (): OtpService => $container->get(OtpService::class),
                new Captcha(self::key()),
                $container->get(Clock::class),
                static fn (): bool => $container->get(Settings::class)
                    ->get(LoginSettings::class)
                    ->requirePhoneVerification
            ))->register();
        });
        // The account is gone; the customer and their appointments stay.
        \add_action('deleted_user', static function (mixed $userId) use ($container): void {
            if (\is_numeric($userId)) {
                $container->get(CustomerRepository::class)->unlinkWpUser((int) $userId);
            }
        });
    }

    /**
     * What signs one-time codes, phone sessions and captchas: WordPress's own
     * salt, so nothing needs to be stored or configured.
     */
    private static function key(): string
    {
        return \wp_salt('auth');
    }
}
