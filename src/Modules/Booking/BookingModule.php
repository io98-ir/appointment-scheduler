<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Log\Logger;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Booking\Application\AppointmentBrowser;
use Vaqtyar\Modules\Booking\Application\AppointmentFactsService;
use Vaqtyar\Modules\Booking\Application\AppointmentPayments;
use Vaqtyar\Modules\Booking\Application\TermsReader;
use Vaqtyar\Modules\Booking\Application\AppointmentService;
use Vaqtyar\Modules\Booking\Application\BookingService;
use Vaqtyar\Modules\Booking\Application\OnlineCheckout;
use Vaqtyar\Modules\Booking\Application\PolicyBookingWindows;
use Vaqtyar\Modules\Booking\Application\UnpaidAppointments;
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
use Vaqtyar\Modules\Booking\Contracts\AppointmentFactsReader;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Catalog\Contracts\CatalogNames;
use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Modules\Payments\Contracts\PaymentsApi;
use Vaqtyar\Modules\Customers\Contracts\CustomerDirectory;
use Vaqtyar\Modules\Scheduling\Contracts\BookingWindows;
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

    /**
     * An appointment waiting for its payment is given up after this long: a gateway's page is good for
     * about 15 minutes, and the reconciliation job (Payments) settles late payments by minute 20.
     */
    private const EXPIRE_UNPAID_AFTER_SECONDS = 1800;
    private const EXPIRE_UNPAID_EVERY_SECONDS = 300;
    private const EXPIRE_UNPAID_BATCH = 50;

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
                self::changed(...),
                $c->get(OnlineCheckout::class),
                $c->get(TermsReader::class)
            )
        );
        $container->singleton(
            TermsReader::class,
            static fn (Container $c) => new WpdbPolicyReader($c->get(Db::class))
        );
        $container->singleton(
            BookingWindows::class,
            static fn (Container $c) => new PolicyBookingWindows($c->get(TermsReader::class))
        );
        $container->singleton(
            UnpaidAppointments::class,
            static fn (Container $c) => new UnpaidAppointments(
                new WpdbAppointmentRepository($c->get(Db::class)),
                $c->get(TransactionRunner::class),
                $c->get(Clock::class),
                self::changed(...)
            )
        );
        $container->singleton(
            AppointmentPayments::class,
            static fn (Container $c) => new AppointmentPayments(
                new WpdbAppointmentRepository($c->get(Db::class)),
                $c->get(PaymentsApi::class),
                $c->get(TermsReader::class),
                new ActionSchedulerBookingJobs(),
                $c->get(TransactionRunner::class),
                $c->get(Clock::class)
            )
        );
        $container->singleton(
            OnlineCheckout::class,
            static fn (Container $c) => new OnlineCheckout(
                $c->get(PaymentsApi::class),
                $c->get(UnpaidAppointments::class)
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
            AppointmentFactsReader::class,
            static fn (Container $c) => new AppointmentFactsService(
                new WpdbAppointmentQuery($c->get(Db::class)),
                $c->get(CustomerDirectory::class),
                $c->get(CatalogNames::class)
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
                $c->get(Clock::class),
                $c->get(PaymentsApi::class)
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
        $this->listenToPayments($container);
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
                static fn (): CatalogApi => $container->get(CatalogApi::class),
                static fn (): bool => $container->get(OnlineCheckout::class)->available(),
                static fn (): TermsReader => $container->get(TermsReader::class)
            ))->register();
            (new TimeRuleRoutes(
                $container->get(Router::class),
                static fn (): TimeRuleAdminService => $container->get(TimeRuleAdminService::class)
            ))->register();
        });
    }

    /**
     * The other half of a payment (booking-engine §7): Payments says a payment went through, and the
     * appointment takes it in: one that waits for it is confirmed (or handed to staff to approve), any
     * other has its payment status brought up to date. One that was cancelled or expired while the
     * customer paid is left as it is: the money is taken, so it is reported to staff with the
     * `{prefix}/booking/needs_attention` action and the log, never decided here. A refund recorded in
     * Payments updates the payment status the same way.
     */
    private function listenToPayments(Container $container): void
    {
        \add_action(
            Hooks::name('payments/succeeded'),
            static function (int $appointmentId, int $paymentId) use ($container): void {
                try {
                    if ($container->get(AppointmentPayments::class)->received($appointmentId)) {
                        return;
                    }
                    $reason = 'The appointment no longer waited for its payment.';
                } catch (\Throwable $e) {
                    $reason = 'The appointment could not be marked paid: ' . $e::class;
                }
                $container->get(Logger::class)->error('booking', $reason, [
                    'appointment_id' => $appointmentId,
                    'payment_id' => $paymentId,
                ]);
                \do_action(Hooks::name('booking/needs_attention'), $appointmentId, $paymentId);
            },
            10,
            2
        );
        \add_action(
            Hooks::name('payments/refunded'),
            static function (int $appointmentId, int $refundId) use ($container): void {
                try {
                    $container->get(AppointmentPayments::class)->changed($appointmentId);
                } catch (\Throwable $e) {
                    $container->get(Logger::class)->error(
                        'booking',
                        'The payment status could not follow a refund: ' . $e::class,
                        ['appointment_id' => $appointmentId, 'refund_id' => $refundId]
                    );
                }
            },
            10,
            2
        );
        $expire = Hooks::name('booking/expire_unpaid');
        \add_action($expire, static function () use ($container): void {
            $container->get(UnpaidAppointments::class)->expireOlderThan(
                self::EXPIRE_UNPAID_AFTER_SECONDS,
                self::EXPIRE_UNPAID_BATCH
            );
        });
        \add_action('admin_init', static function () use ($expire): void {
            if (!\as_has_scheduled_action($expire)) {
                \as_schedule_recurring_action(\time(), self::EXPIRE_UNPAID_EVERY_SECONDS, $expire, [], '', true);
            }
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
