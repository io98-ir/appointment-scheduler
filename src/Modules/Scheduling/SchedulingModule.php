<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Scheduling\Application\AvailabilityService;
use Vaqtyar\Modules\Scheduling\Contracts\OccupancyReader;
use Vaqtyar\Modules\Scheduling\Domain\HolidayRepository;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleExceptionRepository;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRuleRepository;
use Vaqtyar\Modules\Scheduling\Infrastructure\AvailabilitySettings;
use Vaqtyar\Modules\Scheduling\Infrastructure\Migrations\CreateSchedulingTables;
use Vaqtyar\Modules\Scheduling\Infrastructure\Migrations\ImportHolidays;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbHolidayRepository;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbScheduleExceptionRepository;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbScheduleRuleRepository;
use Vaqtyar\Modules\Scheduling\Infrastructure\WpSlotCache;
use Vaqtyar\Modules\Scheduling\Presentation\Rest\AvailabilityRoutes;
use Vaqtyar\Shared\Domain\Clock;

/**
 * Weekly schedules, exceptions and holidays (architecture §3), the shipped
 * holiday datasets, and the public availability API built on them. What is
 * booked comes from OccupancyReader, which the Booking module binds.
 */
final class SchedulingModule implements Module
{
    public function id(): string
    {
        return 'scheduling';
    }

    public function register(Container $container): void
    {
        $container->singleton(
            ScheduleRuleRepository::class,
            static fn (Container $c) => new WpdbScheduleRuleRepository(
                $c->get(Db::class),
                $c->get(Transaction::class),
                $c->get(Clock::class)
            )
        );
        $container->singleton(
            ScheduleExceptionRepository::class,
            static fn (Container $c) => new WpdbScheduleExceptionRepository($c->get(Db::class), $c->get(Clock::class))
        );
        $container->singleton(
            HolidayRepository::class,
            static fn (Container $c) => new WpdbHolidayRepository($c->get(Db::class), $c->get(Clock::class))
        );
        $container->singleton(WpSlotCache::class, static fn () => new WpSlotCache());
        $container->singleton(
            AvailabilityService::class,
            static fn (Container $c) => new AvailabilityService(
                $c->get(CatalogApi::class),
                $c->get(ScheduleRuleRepository::class),
                $c->get(ScheduleExceptionRepository::class),
                $c->get(HolidayRepository::class),
                $c->get(OccupancyReader::class),
                $c->get(WpSlotCache::class),
                $c->get(Clock::class),
                $c->get(Settings::class)->get(AvailabilitySettings::class)->defaults()
            )
        );
    }

    /**
     * @return list<\Vaqtyar\Kernel\Database\Migration>
     */
    public function migrations(): array
    {
        return [new CreateSchedulingTables(), new ImportHolidays(1405)];
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
        $invalidate = static function () use ($container): void {
            $container->get(WpSlotCache::class)->invalidate();
        };
        \add_action(Hooks::name('catalog/changed'), $invalidate);
        \add_action(Hooks::name('scheduling/changed'), $invalidate);
        \add_action('rest_api_init', static function () use ($container): void {
            (new AvailabilityRoutes(
                $container->get(Router::class),
                static fn (): AvailabilityService => $container->get(AvailabilityService::class)
            ))->register();
        });
    }
}
