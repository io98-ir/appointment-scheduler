<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Booking\Application\HoldPricing;
use Vaqtyar\Modules\Booking\Application\HoldService;
use Vaqtyar\Modules\Booking\Infrastructure\Migrations\CreateBookingTables;
use Vaqtyar\Modules\Booking\Infrastructure\Migrations\CreateOccupanciesTable;
use Vaqtyar\Modules\Booking\Infrastructure\Migrations\CreateResourceDayLocksTable;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbHoldRepository;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbOccupancyReader;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbPricingReader;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbResourceLocker;
use Vaqtyar\Modules\Booking\Infrastructure\PricingSettings;
use Vaqtyar\Modules\Booking\Presentation\Rest\HoldRoutes;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Scheduling\Contracts\OccupancyReader;
use Vaqtyar\Modules\Scheduling\Contracts\SlotClaims;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\TransactionRunner;

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
                static function (): void {
                    \do_action(Hooks::name('booking/changed'));
                }
            )
        );
    }

    /**
     * @return list<\Vaqtyar\Kernel\Database\Migration>
     */
    public function migrations(): array
    {
        return [new CreateOccupanciesTable(), new CreateBookingTables(), new CreateResourceDayLocksTable()];
    }

    /**
     * @return array<string, list<string>>
     */
    public function capabilities(): array
    {
        return [];
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
        });
    }
}
