<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Booking\Application\AppointmentBrowser;
use Vaqtyar\Modules\Booking\Application\AppointmentService;
use Vaqtyar\Modules\Booking\Application\BookingService;
use Vaqtyar\Modules\Booking\Application\CouponAdminService;
use Vaqtyar\Modules\Booking\Application\CustomerPanel;
use Vaqtyar\Modules\Booking\Application\FieldAdminService;
use Vaqtyar\Modules\Booking\Application\HoldPricing;
use Vaqtyar\Modules\Booking\Application\FieldReader;
use Vaqtyar\Modules\Booking\Application\HoldService;
use Vaqtyar\Modules\Booking\Application\PolicyAdminService;
use Vaqtyar\Modules\Booking\Application\ReportService;
use Vaqtyar\Modules\Booking\Application\TimeRuleAdminService;
use Vaqtyar\Modules\Booking\Domain\Field\FieldRepository;
use Vaqtyar\Modules\Booking\Infrastructure\Jobs\ActionSchedulerBookingJobs;
use Vaqtyar\Modules\Booking\Infrastructure\Migrations\AddAppointmentStartIndex;
use Vaqtyar\Modules\Booking\Infrastructure\Migrations\CreateBookingTables;
use Vaqtyar\Modules\Booking\Infrastructure\Migrations\CreateOccupanciesTable;
use Vaqtyar\Modules\Booking\Infrastructure\Migrations\CreateResourceDayLocksTable;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbAppointmentRepository;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbCouponRepository;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbFieldReader;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbFieldRepository;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbHoldRepository;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbOccupancyReader;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbPolicyReader;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbPolicyRepository;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbPricingReader;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbResourceLocker;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbTimeRuleRepository;
use Vaqtyar\Modules\Booking\Infrastructure\PricingSettings;
use Vaqtyar\Modules\Booking\Infrastructure\Query\WpdbAppointmentQuery;
use Vaqtyar\Modules\Booking\Infrastructure\Query\WpdbReportQuery;
use Vaqtyar\Modules\Booking\Presentation\Rest\AppointmentListRoutes;
use Vaqtyar\Modules\Booking\Presentation\Rest\AppointmentRoutes;
use Vaqtyar\Modules\Booking\Presentation\Rest\BookingRoutes;
use Vaqtyar\Modules\Booking\Presentation\Rest\CouponRoutes;
use Vaqtyar\Modules\Booking\Presentation\Rest\FieldRoutes;
use Vaqtyar\Modules\Booking\Presentation\Rest\GuestBookingRoutes;
use Vaqtyar\Modules\Booking\Presentation\Rest\HoldRoutes;
use Vaqtyar\Modules\Booking\Presentation\Rest\PanelRoutes;
use Vaqtyar\Modules\Booking\Presentation\Rest\PolicyRoutes;
use Vaqtyar\Modules\Booking\Presentation\Rest\ReportRoutes;
use Vaqtyar\Modules\Booking\Presentation\Rest\TimeRuleRoutes;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Modules\Customers\Contracts\CustomerDirectory;
use Vaqtyar\Modules\Scheduling\Contracts\OccupancyReader;
use Vaqtyar\Modules\Scheduling\Contracts\SlotClaims;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Shared\WpAuthorizer;

/**
 * Holds, appointments and their state (architecture §3). Every write to the
 * occupancies fires the booking/changed action, which clears the cached
 * availability.
 */
final class BookingModule implements Module
{
    /** Expired holds are purged this often; correctness does not wait for it. */
    private const PURGE_EVERY_SECONDS = 600;

    public function id(): string
    {
        return 'booking';
    }

    public function register(Container $container): void
    {
        $container->singleton(
            OccupancyReader::class,
            static fn (Container $c) => new WpdbOccupancyReader($c->get(Db::class), $c->get(Clock::class))
        );
        $container->singleton(
            HoldService::class,
            static fn (Container $c) => new HoldService(
                $c->get(SlotClaims::class),
                new HoldPricing(
                    $c->get(CatalogApi::class),
                    new WpdbPricingReader($c->get(Db::class)),
                    $c->get(Settings::class)->get(PricingSettings::class)->roundingStep,
                    $c->get(Settings::class)->get(PricingSettings::class)->rounding
                ),
                new WpdbResourceLocker($c->get(Db::class)),
                new WpdbHoldRepository($c->get(Db::class)),
                $c->get(TransactionRunner::class),
                $c->get(Clock::class),
                self::changed(...)
            )
        );
        $container->singleton(
            BookingService::class,
            static fn (Container $c) => new BookingService(
                $c->get(CatalogApi::class),
                $c->get(CustomerApi::class),
                new WpdbPricingReader($c->get(Db::class)),
                new WpdbFieldReader($c->get(Db::class)),
                new WpdbResourceLocker($c->get(Db::class)),
                new WpdbHoldRepository($c->get(Db::class)),
                new WpdbAppointmentRepository($c->get(Db::class)),
                new ActionSchedulerBookingJobs(),
                $c->get(TransactionRunner::class),
                $c->get(Clock::class),
                new WpAuthorizer(),
                self::changed(...)
            )
        );
        $container->singleton(
            AppointmentService::class,
            static fn (Container $c) => new AppointmentService(
                new WpdbAppointmentRepository($c->get(Db::class)),
                new WpdbPolicyReader($c->get(Db::class)),
                $c->get(SlotClaims::class),
                new WpdbResourceLocker($c->get(Db::class)),
                new ActionSchedulerBookingJobs(),
                $c->get(TransactionRunner::class),
                $c->get(Clock::class),
                new WpAuthorizer(),
                self::changed(...)
            )
        );
        $container->singleton(
            AppointmentBrowser::class,
            static fn (Container $c) => new AppointmentBrowser(
                new WpdbAppointmentQuery($c->get(Db::class)),
                $c->get(CustomerDirectory::class),
                new WpAuthorizer()
            )
        );
        $container->singleton(
            PolicyAdminService::class,
            static fn (Container $c) => new PolicyAdminService(
                new WpAuthorizer(),
                $c->get(CatalogApi::class),
                new WpdbPolicyRepository($c->get(Db::class), $c->get(Transaction::class), $c->get(Clock::class))
            )
        );
        $container->singleton(
            FieldRepository::class,
            static fn (Container $c) => new WpdbFieldRepository($c->get(Db::class), $c->get(Clock::class))
        );
        $container->singleton(
            FieldAdminService::class,
            static fn (Container $c) => new FieldAdminService(
                new WpAuthorizer(),
                $c->get(CatalogApi::class),
                $c->get(FieldRepository::class)
            )
        );
        $container->singleton(
            CouponAdminService::class,
            static fn (Container $c) => new CouponAdminService(
                new WpAuthorizer(),
                $c->get(CatalogApi::class),
                new WpdbCouponRepository($c->get(Db::class), $c->get(Clock::class))
            )
        );
        $container->singleton(
            CustomerPanel::class,
            static fn (Container $c) => new CustomerPanel(
                $c->get(CustomerApi::class),
                new WpdbAppointmentQuery($c->get(Db::class)),
                $c->get(AppointmentService::class),
                $c->get(Clock::class)
            )
        );
        $container->singleton(
            ReportService::class,
            static fn (Container $c) => new ReportService(new WpdbReportQuery($c->get(Db::class)), new WpAuthorizer())
        );
        $container->singleton(
            TimeRuleAdminService::class,
            static fn (Container $c) => new TimeRuleAdminService(
                new WpAuthorizer(),
                $c->get(CatalogApi::class),
                new WpdbTimeRuleRepository($c->get(Db::class), $c->get(Clock::class))
            )
        );
    }

    /**
     * @return list<\Vaqtyar\Kernel\Database\Migration>
     */
    public function migrations(): array
    {
        return [
            new CreateOccupanciesTable(),
            new CreateBookingTables(),
            new CreateResourceDayLocksTable(),
            new AddAppointmentStartIndex(),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function capabilities(): array
    {
        return [
            BookingService::CAPABILITY => ['administrator'],
            AppointmentService::OVERRIDE_CAPABILITY => ['administrator'],
        ];
    }

    public function boot(Context $context): void
    {
        $container = $context->container;
        $purge = Hooks::name('booking/purge_holds');
        \add_action($purge, static function () use ($container): void {
            $container->get(HoldService::class)->purgeExpired();
        });
        // Checked on admin requests only, which Action Scheduler's own runner is.
        \add_action('admin_init', static function () use ($purge): void {
            if (!\as_has_scheduled_action($purge)) {
                \as_schedule_recurring_action(\time(), self::PURGE_EVERY_SECONDS, $purge, [], '', true);
            }
        });
        \add_action('rest_api_init', static function () use ($container): void {
            (new HoldRoutes(
                $container->get(Router::class),
                static fn (): HoldService => $container->get(HoldService::class)
            ))->register();
            (new BookingRoutes(
                $container->get(Router::class),
                static fn (): BookingService => $container->get(BookingService::class)
            ))->register();
            (new AppointmentRoutes(
                $container->get(Router::class),
                static fn (): AppointmentService => $container->get(AppointmentService::class)
            ))->register();
            (new AppointmentListRoutes(
                $container->get(Router::class),
                static fn (): AppointmentBrowser => $container->get(AppointmentBrowser::class)
            ))->register();
            (new PolicyRoutes(
                $container->get(Router::class),
                static fn (): PolicyAdminService => $container->get(PolicyAdminService::class)
            ))->register();
            (new FieldRoutes(
                $container->get(Router::class),
                static fn (): FieldAdminService => $container->get(FieldAdminService::class)
            ))->register();
            (new CouponRoutes(
                $container->get(Router::class),
                static fn (): CouponAdminService => $container->get(CouponAdminService::class)
            ))->register();
            (new PanelRoutes(
                $container->get(Router::class),
                static fn (): CustomerPanel => $container->get(CustomerPanel::class)
            ))->register();
            (new ReportRoutes(
                $container->get(Router::class),
                static fn (): ReportService => $container->get(ReportService::class)
            ))->register();
            (new GuestBookingRoutes(
                $container->get(Router::class),
                static fn (): BookingService => $container->get(BookingService::class),
                static fn (): FieldReader => new WpdbFieldReader($container->get(Db::class)),
                static fn (): CatalogApi => $container->get(CatalogApi::class)
            ))->register();
            (new TimeRuleRoutes(
                $container->get(Router::class),
                static fn (): TimeRuleAdminService => $container->get(TimeRuleAdminService::class)
            ))->register();
        });
    }

    /**
     * Tells availability the occupancies changed, after a commit.
     */
    private static function changed(): void
    {
        \do_action(Hooks::name('booking/changed'));
    }
}
