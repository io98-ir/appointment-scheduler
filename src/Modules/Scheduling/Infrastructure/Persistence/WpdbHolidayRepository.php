<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Scheduling\Domain\Holiday;
use Vaqtyar\Modules\Scheduling\Domain\HolidayRepository;
use Vaqtyar\Modules\Scheduling\Domain\HolidaySource;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\Slug;

final class WpdbHolidayRepository implements HolidayRepository
{
    public function __construct(private readonly Db $db, private readonly Clock $clock)
    {
    }

    /**
     * @return list<Holiday>
     */
    public function between(Slug $calendar, LocalDate $from, LocalDate $to): array
    {
        $rows = $this->db->getResults(
            'SELECT * FROM %i WHERE calendar = %s AND local_date BETWEEN %s AND %s ORDER BY local_date',
            Tables::name('holidays'),
            $calendar->value,
            $from->toString(),
            $to->toString()
        );

        return \array_map(static fn (array $row): Holiday => self::fromRow(new Row($row)), $rows);
    }

    /**
     * One upsert on the (calendar, local_date) key. VALUES() rather than the
     * row alias of MySQL 8.0.19+, which MariaDB lacks.
     */
    public function save(Holiday $holiday): void
    {
        $now = Columns::now($this->clock);
        $this->db->execute(
            'INSERT INTO %i (calendar, local_date, title, source, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE title = VALUES(title), source = VALUES(source), updated_at = VALUES(updated_at)',
            Tables::name('holidays'),
            $holiday->calendar->value,
            $holiday->date->toString(),
            $holiday->title->value,
            $holiday->source->value,
            $now,
            $now
        );
    }

    public function delete(Slug $calendar, LocalDate $date): void
    {
        $this->db->execute(
            'DELETE FROM %i WHERE calendar = %s AND local_date = %s',
            Tables::name('holidays'),
            $calendar->value,
            $date->toString()
        );
    }

    /**
     * "ON DUPLICATE KEY UPDATE id = id" keeps the stored day and reports no
     * affected row for it. INSERT IGNORE would also turn other errors into
     * warnings.
     *
     * @param list<Holiday> $holidays
     */
    public function import(array $holidays): int
    {
        $now = Columns::now($this->clock);
        $added = 0;
        foreach ($holidays as $holiday) {
            $added += $this->db->execute(
                'INSERT INTO %i (calendar, local_date, title, source, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE id = id',
                Tables::name('holidays'),
                $holiday->calendar->value,
                $holiday->date->toString(),
                $holiday->title->value,
                $holiday->source->value,
                $now,
                $now
            );
        }

        return $added;
    }

    private static function fromRow(Row $row): Holiday
    {
        return new Holiday(
            Slug::fromInput($row->string('calendar')),
            LocalDate::fromString($row->string('local_date')),
            Name::fromInput($row->string('title')),
            HolidaySource::from($row->string('source')),
        );
    }
}
