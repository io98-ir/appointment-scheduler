<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\FieldReader;
use Vaqtyar\Modules\Booking\Domain\Field\Field;
use Vaqtyar\Modules\Booking\Domain\Field\FieldType;
use Vaqtyar\Modules\Booking\Domain\Field\ShowIf;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * FieldReader on the fields table: every global field and the service's
 * own, in `sort` order. The admin screen (T3.5) writes only what the
 * Domain accepts; a row that no longer parses is skipped, like a broken
 * price rule or policy.
 */
final class WpdbFieldReader implements FieldReader
{
    private const GLOBAL = 'global';

    private const SERVICE = 'service';

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * @return list<Field>
     */
    public function forService(int $serviceId): array
    {
        $rows = $this->db->getResults(
            'SELECT field_key, type, label, required, options, show_if, sort FROM %i
            WHERE scope = %s OR (scope = %s AND service_id = %d)
            ORDER BY sort, id',
            Tables::name('fields'),
            self::GLOBAL,
            self::SERVICE,
            $serviceId
        );
        $fields = [];
        foreach ($rows as $values) {
            $field = self::field(new Row($values));
            if (null !== $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private static function field(Row $row): ?Field
    {
        try {
            return new Field(
                $row->string('field_key'),
                FieldType::from($row->string('type')),
                $row->string('label'),
                1 === $row->int('required'),
                self::options($row->stringOrNull('options')),
                self::showIf($row->stringOrNull('show_if')),
                $row->int('sort')
            );
        } catch (InvalidValue | \ValueError) {
            return null;
        }
    }

    /**
     * @return list<string>
     * @throws InvalidValue invalid_field when the column is not a JSON list of strings.
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

    /**
     * @throws InvalidValue invalid_field when the column is not a simple {field, equals} condition.
     */
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
}
