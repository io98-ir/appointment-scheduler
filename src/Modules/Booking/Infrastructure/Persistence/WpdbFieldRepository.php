<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Domain\Field\Field;
use Vaqtyar\Modules\Booking\Domain\Field\FieldDefinition;
use Vaqtyar\Modules\Booking\Domain\Field\FieldRepository;
use Vaqtyar\Modules\Booking\Domain\Field\FieldScope;
use Vaqtyar\Modules\Booking\Domain\Field\FieldType;
use Vaqtyar\Modules\Booking\Domain\Field\ShowIf;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * FieldRepository for the admin screen (T3.5), separate from
 * WpdbFieldReader (the booking-time read path): this one keeps the row id
 * and scope/service_id an entity needs for CRUD, and writes. A row that no
 * longer parses into a Field is skipped from a listing, the same tolerance
 * WpdbFieldReader has.
 */
final class WpdbFieldRepository implements FieldRepository
{
    private const GLOBAL = 'global';
    private const SERVICE = 'service';

    public function __construct(private readonly Db $db, private readonly Clock $clock)
    {
    }

    /**
     * @return list<FieldDefinition>
     */
    public function globalFields(): array
    {
        return $this->list(
            'SELECT * FROM %i WHERE scope = %s ORDER BY sort, id',
            Tables::name('fields'),
            self::GLOBAL
        );
    }

    /**
     * @return list<FieldDefinition>
     */
    public function forService(int $serviceId): array
    {
        return $this->list(
            'SELECT * FROM %i WHERE scope = %s AND service_id = %d ORDER BY sort, id',
            Tables::name('fields'),
            self::SERVICE,
            $serviceId
        );
    }

    public function find(int $id): ?FieldDefinition
    {
        $rows = $this->db->getResults('SELECT * FROM %i WHERE id = %d', Tables::name('fields'), $id);

        return [] === $rows ? null : self::definition(new Row($rows[0]));
    }

    public function save(FieldDefinition $definition): FieldDefinition
    {
        $field = $definition->field;
        $now = $this->now();
        $values = [
            'scope' => $definition->scope->value,
            'service_id' => $definition->serviceId,
            'field_key' => $field->key,
            'type' => $field->type->value,
            'label' => $field->label,
            'required' => $field->required ? 1 : 0,
            'options' => [] === $field->options ? null : (string) \wp_json_encode($field->options),
            'show_if' => null === $field->showIf
                ? null
                : (string) \wp_json_encode(['field' => $field->showIf->fieldKey, 'equals' => $field->showIf->equals]),
            'sort' => $field->sort,
            'updated_at' => $now,
        ];
        $table = Tables::name('fields');
        if (null === $definition->id) {
            $id = $this->db->insert($table, $values + ['created_at' => $now]);
        } else {
            $id = $definition->id;
            $this->db->update($table, $values, ['id' => $id]);
        }

        return new FieldDefinition($id, $definition->scope, $definition->serviceId, $field);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM %i WHERE id = %d', Tables::name('fields'), $id);
    }

    /**
     * @return list<FieldDefinition>
     */
    private function list(string $sql, int|string ...$args): array
    {
        $rows = $this->db->getResults($sql, ...$args);
        $definitions = [];
        foreach ($rows as $values) {
            $definition = self::definition(new Row($values));
            if (null !== $definition) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    private static function definition(Row $row): ?FieldDefinition
    {
        try {
            $field = new Field(
                $row->string('field_key'),
                FieldType::from($row->string('type')),
                $row->string('label'),
                1 === $row->int('required'),
                self::options($row->stringOrNull('options')),
                self::showIf($row->stringOrNull('show_if')),
                $row->int('sort')
            );

            return new FieldDefinition(
                $row->int('id'),
                FieldScope::from($row->string('scope')),
                $row->intOrNull('service_id'),
                $field
            );
        } catch (InvalidValue | \ValueError) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private static function options(?string $json): array
    {
        if (null === $json) {
            return [];
        }
        $decoded = \json_decode($json, true);
        if (!\is_array($decoded)) {
            throw new InvalidValue('invalid_field', 'The field options are not a list.');
        }
        $options = [];
        foreach ($decoded as $value) {
            if (!\is_string($value)) {
                throw new InvalidValue('invalid_field', 'A field option is not a string.');
            }
            $options[] = $value;
        }

        return $options;
    }

    private static function showIf(?string $json): ?ShowIf
    {
        if (null === $json) {
            return null;
        }
        $decoded = \json_decode($json, true);
        $field = \is_array($decoded) ? ($decoded['field'] ?? null) : null;
        $equals = \is_array($decoded) ? ($decoded['equals'] ?? null) : null;
        if (!\is_string($field) || !\is_string($equals)) {
            throw new InvalidValue('invalid_field', 'show_if needs a field and an equals string.');
        }

        return new ShowIf($field, $equals);
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
