<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Field;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * Validates a booking's custom field answers against its service's fields
 * (T2.6), in order. A field whose show_if does not match the answers
 * validated before it is hidden: never required, never stored, whatever the
 * client sent for it. So a hidden field's answer reveals nothing further,
 * and a condition compares the normalized value ('0' for an unchecked box).
 */
final class AnswerValidator
{
    /**
     * @param list<Field> $fields in the order they are shown; a condition
     *     on a later field never matches.
     * @param array<string, mixed> $raw by field_key, exactly as the client sent them.
     * @return array<string, string> validated answers, one per visible field that was answered.
     * @throws InvalidValue answer_required or invalid_answer, on the first field that fails.
     */
    public static function validate(array $fields, array $raw): array
    {
        $answers = [];
        foreach ($fields as $field) {
            if (null !== $field->showIf && !$field->showIf->matches($answers)) {
                continue;
            }
            $value = $raw[$field->key] ?? null;
            if (null === $value || '' === $value) {
                if ($field->required) {
                    throw $field->requiredAnswer();
                }
                continue;
            }
            $answers[$field->key] = $field->validate($value);
        }

        return $answers;
    }
}
