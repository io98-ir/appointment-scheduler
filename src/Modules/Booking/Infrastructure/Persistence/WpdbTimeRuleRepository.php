<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Domain\Pricing\TimeRule;
use Vaqtyar\Modules\Booking\Domain\Pricing\TimeRuleDefinition;
use Vaqtyar\Modules\Booking\Domain\Pricing\TimeRuleRepository;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;

/**
 * TimeRuleRepository for the admin screen (T3.5), separate from
 * WpdbPricingReader (the booking-time read path). It writes the config JSON
 * that reader documents; a row that no longer parses is skipped from a
 * listing, the same tolerance the reader has.
 */
final class WpdbTimeRuleRepository implements TimeRuleRepository
{
    private const TYPE_TIME = 'time';
    private const ACTIVE = 'active';
    private const INACTIVE = 'inactive';

    public function __construct(private readonly Db $db, private readonly Clock $clock)
    {
    }

    /**
     * @return list<TimeRuleDefinition>
     */
    public function all(): array
    {
        $rows = $this->db->getResults(
            'SELECT * FROM %i WHERE type = %s ORDER BY priority DESC, id',
            Tables::name('price_rules'),
            self::TYPE_TIME
        );
        $definitions = [];
        foreach ($rows as $values) {
            $definition = self::definition(new Row($values));
            if (null !== $definition) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    public function find(int $id): ?TimeRuleDefinition
    {
        $rows = $this->db->getResults(
            'SELECT * FROM %i WHERE id = %d AND type = %s',
            Tables::name('price_rules'),
            $id,
            self::TYPE_TIME
        );

        return [] === $rows ? null : self::definition(new Row($rows[0]));
    }

    public function save(TimeRuleDefinition $definition): TimeRuleDefinition
    {
        $rule = $definition->rule;
        $now = $this->now();
        $values = [
            'service_id' => $definition->serviceId,
            'config' => (string) \wp_json_encode([
                'weekdays' => $rule->weekdays,
                'from' => LocalTime::fromMinutes($rule->fromMin)->toString(),
                'to' => LocalTime::fromMinutes($rule->toMin)->toString(),
                'valid_from' => $rule->validFrom?->toString(),
                'valid_to' => $rule->validTo?->toString(),
                'percent' => $rule->percent,
            ]),
            'priority' => $definition->priority,
            'status' => $definition->active ? self::ACTIVE : self::INACTIVE,
            'updated_at' => $now,
        ];
        $table = Tables::name('price_rules');
        if (0 === $definition->id) {
            $id = $this->db->insert($table, $values + ['type' => self::TYPE_TIME, 'created_at' => $now]);
        } else {
            $id = $definition->id;
            $this->db->update($table, $values, ['id' => $id, 'type' => self::TYPE_TIME]);
        }

        return new TimeRuleDefinition(
            $id,
            $definition->serviceId,
            $definition->priority,
            $definition->active,
            new TimeRule(
                $id,
                $rule->weekdays,
                $rule->fromMin,
                $rule->toMin,
                $rule->validFrom,
                $rule->validTo,
                $rule->percent
            )
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute(
            'DELETE FROM %i WHERE id = %d AND type = %s',
            Tables::name('price_rules'),
            $id,
            self::TYPE_TIME
        );
    }

    private static function definition(Row $row): ?TimeRuleDefinition
    {
        $config = \json_decode($row->string('config'), true);
        if (!\is_array($config)) {
            return null;
        }
        $weekdays = $config['weekdays'] ?? [];
        $from = $config['from'] ?? null;
        $to = $config['to'] ?? null;
        $percent = $config['percent'] ?? null;
        if (!\is_array($weekdays) || !\is_string($from) || !\is_string($to) || !\is_int($percent)) {
            return null;
        }
        try {
            $id = $row->int('id');

            return new TimeRuleDefinition(
                $id,
                $row->intOrNull('service_id'),
                $row->int('priority'),
                self::ACTIVE === $row->string('status'),
                new TimeRule(
                    $id,
                    \array_values(\array_filter($weekdays, 'is_int')),
                    LocalTime::fromString($from)->minutes,
                    LocalTime::fromString($to)->minutes,
                    self::date($config['valid_from'] ?? null),
                    self::date($config['valid_to'] ?? null),
                    $percent
                )
            );
        } catch (InvalidValue) {
            return null;
        }
    }

    private static function date(mixed $value): ?LocalDate
    {
        return \is_string($value) ? LocalDate::fromString($value) : null;
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
