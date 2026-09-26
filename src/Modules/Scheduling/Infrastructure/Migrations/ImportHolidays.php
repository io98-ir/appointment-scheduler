<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Modules\Scheduling\Infrastructure\HolidayDataset;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbHolidayRepository;
use Vaqtyar\Shared\SystemClock;

/**
 * Adds one year's shipped holiday dataset. Each new year's dataset is a new
 * migration, so it reaches existing sites with the update. Days already
 * stored are kept, so running it again changes nothing.
 */
final class ImportHolidays implements Migration
{
    /**
     * @param int $year The Jalali year of assets/holidays/{year}.json.
     */
    public function __construct(private readonly int $year)
    {
    }

    public function up(Db $db): void
    {
        (new WpdbHolidayRepository($db, new SystemClock()))
            ->import(HolidayDataset::fromFile(HolidayDataset::path($this->year), $this->year));
    }
}
