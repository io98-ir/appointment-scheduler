<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Modules\Scheduling\Domain\HolidayRepository;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleExceptionRepository;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRuleRepository;
use Vaqtyar\Modules\Scheduling\Infrastructure\Migrations\CreateSchedulingTables;
use Vaqtyar\Modules\Scheduling\Infrastructure\Migrations\ImportHolidays;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbHolidayRepository;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbScheduleExceptionRepository;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbScheduleRuleRepository;
use Vaqtyar\Shared\Domain\Clock;

/**
 * Weekly schedules, exceptions and holidays (architecture §3), and the
 * shipped holiday datasets. Availability (T1.4, T1.5) builds on them.
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
    }
}
