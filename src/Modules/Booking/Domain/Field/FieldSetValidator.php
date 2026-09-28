<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Field;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * Checks a field against the "available set" it would join at booking time
 * (implementation-notes §4.13): global fields, plus one service's own for a
 * service-scoped field. `FieldReader::forService()` does not dedupe or
 * check `show_if`, so the admin screen must reject here what it would
 * otherwise silently mis-evaluate later (a second field with the same key
 * overwriting the first's answer, or a condition that never becomes true).
 *
 * A global field's set is the globals only: it cannot know a particular
 * service's own fields, since it applies to all of them at once. Its
 * `show_if` may therefore reference only an earlier global field, never a
 * service one.
 */
final class FieldSetValidator
{
    /**
     * @param list<FieldDefinition> $set
     */
    public static function validate(array $set): void
    {
        $keys = [];
        foreach ($set as $definition) {
            $key = $definition->field->key;
            if (isset($keys[$key])) {
                throw new InvalidValue('duplicate_field_key', "Two fields share the key \"{$key}\".");
            }
            $keys[$key] = true;
        }
        foreach ($set as $definition) {
            self::checkShowIf($set, $definition);
        }
    }

    /**
     * @param list<FieldDefinition> $set
     */
    private static function checkShowIf(array $set, FieldDefinition $definition): void
    {
        $showIf = $definition->field->showIf;
        if (null === $showIf) {
            return;
        }
        if ($showIf->fieldKey === $definition->field->key) {
            throw new InvalidValue('invalid_show_if', 'A field cannot depend on its own answer.');
        }
        foreach ($set as $other) {
            if ($other->field->key === $showIf->fieldKey && $other->field->sort < $definition->field->sort) {
                return;
            }
        }
        throw new InvalidValue(
            'invalid_show_if',
            'A field can depend only on an earlier field\'s answer, by sort order.'
        );
    }
}
