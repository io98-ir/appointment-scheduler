<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Scheduling\Domain\ExceptionKind;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleException;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleExceptionRepository;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\LocalDate;

final class WpdbScheduleExceptionRepository implements ScheduleExceptionRepository
{
    public function __construct(private readonly Db $db, private readonly Clock $clock)
    {
    }

    public function find(int $id): ?ScheduleException
    {
        $rows = $this->db->getResults('SELECT * FROM %i WHERE id = %d', Tables::name('schedule_exceptions'), $id);

        return [] === $rows ? null : self::fromRow(new Row($rows[0]));
    }

    /**
     * @param list<Owner> $owners
     * @return list<ScheduleException>
     */
    public function between(array $owners, LocalDate $from, LocalDate $to): array
    {
        $exceptions = [];
        foreach (Columns::idsByType($owners) as $type => $ids) {
            // One %d per id, so the ids still go through prepare().
            $in = \implode(',', \array_fill(0, \count($ids), '%d'));
            $rows = $this->db->getResults(
                'SELECT * FROM %i WHERE owner_type = %s AND owner_id IN (' . $in . ') AND local_date BETWEEN %s AND %s',
                Tables::name('schedule_exceptions'),
                $type,
                ...[...$ids, $from->toString(), $to->toString()]
            );
            foreach ($rows as $row) {
                $exceptions[] = self::fromRow(new Row($row));
            }
        }
        // By date and start (a whole day first), across the owner types.
        \usort(
            $exceptions,
            static fn (ScheduleException $a, ScheduleException $b): int
                => [$a->date->toString(), $a->start->minutes ?? -1, $a->id]
                <=> [$b->date->toString(), $b->start->minutes ?? -1, $b->id]
        );

        return $exceptions;
    }

    public function save(ScheduleException $exception): ScheduleException
    {
        $table = Tables::name('schedule_exceptions');
        $now = Columns::now($this->clock);
        $columns = [
            'owner_type' => $exception->owner->type->value,
            'owner_id' => $exception->owner->id,
            'local_date' => $exception->date->toString(),
            'start_time' => null === $exception->start ? null : Columns::time($exception->start),
            'end_time' => null === $exception->end ? null : Columns::time($exception->end),
            'kind' => $exception->kind->value,
            'note' => $exception->note,
            'updated_at' => $now,
        ];
        $id = $exception->id;
        if (null === $id) {
            $id = $this->db->insert($table, $columns + ['created_at' => $now]);
        } else {
            $this->db->update($table, $columns, ['id' => $id]);
        }

        return $this->find($id) ?? throw new \LogicException('The exception just saved is gone.');
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM %i WHERE id = %d', Tables::name('schedule_exceptions'), $id);
    }

    private static function fromRow(Row $row): ScheduleException
    {
        $start = $row->stringOrNull('start_time');
        $end = $row->stringOrNull('end_time');

        return new ScheduleException(
            $row->int('id'),
            Columns::owner($row),
            LocalDate::fromString($row->string('local_date')),
            null === $start ? null : Columns::localTime($start),
            null === $end ? null : Columns::localTime($end),
            ExceptionKind::from($row->string('kind')),
            $row->string('note'),
        );
    }
}
