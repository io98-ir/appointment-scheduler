<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Modules\Booking\Infrastructure\Migrations\CreateOccupanciesTable;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbOccupancyReader;
use Vaqtyar\Modules\Scheduling\Contracts\OccupancyReader;
use Vaqtyar\Shared\Domain\Clock;

/**
 * Holds, appointments and their state (architecture §3). For now only the
 * occupancies that availability reads; holds and appointments come in M2.
 */
final class BookingModule implements Module
{
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
    }

    /**
     * @return list<\Vaqtyar\Kernel\Database\Migration>
     */
    public function migrations(): array
    {
        return [new CreateOccupanciesTable()];
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
