<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Field;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * Validates a booking's custom field answers against its service's fields
 * (T2.6). A field whose show_if does not match the raw answers is hidden:
 * never required, never stored, whatever the client sent for it.
 */
final class AnswerValidator
{
    /**
     * @param list<Field> $fields in the order they are shown, so a
     *     condition can rely on an earlier field's answer.
     * @param array<string, mixed> $raw by field_key, exactly as the client sent them.
     * @return array<string, string> validated answers, one per visible field that was answered.
     * @throws InvalidValue answer_required or invalid_answer, on the first field that fails.
     */
    public static function validate(array $fields, array $raw): array
    {
        $answers = [];
        foreach ($fields as $field) {
            if (null !== $field->showIf && !$field->showIf->matches($raw)) {
                continue;
            }
            $value = $raw[$field->key] ?? null;
            if (null === $value || '' === $value) {
                if ($field->required) {
                    throw new InvalidValue('answer_required', "The field \"{$field->label}\" is required.");
                }
                continue;
            }
            $answers[$field->key] = $field->validate($value);
        }

        return $answers;
    }
}
