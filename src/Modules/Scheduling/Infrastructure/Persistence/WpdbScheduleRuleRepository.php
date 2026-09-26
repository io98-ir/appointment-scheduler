<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\RuleKind;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRuleRepository;
use Vaqtyar\Shared\Domain\Clock;

final class WpdbScheduleRuleRepository implements ScheduleRuleRepository
{
    public function __construct(
        private readonly Db $db,
        private readonly Transaction $transaction,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param list<Owner> $owners
     * @return list<ScheduleRule>
     */
    public function ofOwners(array $owners): array
    {
        $rules = [];
        foreach (Columns::idsByType($owners) as $type => $ids) {
            // One %d per id, so the ids still go through prepare().
            $in = \implode(',', \array_fill(0, \count($ids), '%d'));
            $rows = $this->db->getResults(
                'SELECT * FROM %i WHERE owner_type = %s AND owner_id IN (' . $in . ')
                ORDER BY owner_id, weekday, start_time, id',
                Tables::name('schedule_rules'),
                $type,
                ...$ids
            );
            foreach ($rows as $row) {
                $rules[] = self::fromRow(new Row($row));
            }
        }

        return $rules;
    }

    /**
     * Delete and insert in one transaction. Two saves of one owner either
     * wait on the DELETE's row locks or, when the owner has no rows yet
     * (gap locks only), deadlock and are retried by Transaction; the last
     * one wins either way. Do not rely on the DELETE as a lock.
     *
     * @param list<ScheduleRule> $rules
     */
    public function replace(Owner $owner, array $rules): void
    {
        foreach ($rules as $rule) {
            if (!$rule->owner->equals($owner)) {
                throw new \LogicException('A weekly schedule is replaced with rules of its own owner.');
            }
        }
        $this->transaction->run(function () use ($owner, $rules): void {
            $table = Tables::name('schedule_rules');
            $this->db->execute(
                'DELETE FROM %i WHERE owner_type = %s AND owner_id = %d',
                $table,
                $owner->type->value,
                $owner->id
            );
            $now = Columns::now($this->clock);
            foreach ($rules as $rule) {
                $this->db->insert($table, [
                    'owner_type' => $owner->type->value,
                    'owner_id' => $owner->id,
                    'weekday' => $rule->weekday,
                    'start_time' => Columns::time($rule->start),
                    'end_time' => Columns::time($rule->end),
                    'kind' => $rule->kind->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    private static function fromRow(Row $row): ScheduleRule
    {
        return new ScheduleRule(
            $row->int('id'),
            Columns::owner($row),
            $row->int('weekday'),
            Columns::localTime($row->string('start_time')),
            Columns::localTime($row->string('end_time')),
            RuleKind::from($row->string('kind')),
        );
    }
}
