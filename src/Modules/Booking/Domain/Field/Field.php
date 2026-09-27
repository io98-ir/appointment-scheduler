<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Field;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\PersianDigits;

/**
 * A custom field definition (data-model §2, `fields`): global, or scoped to
 * one service by whoever built the list this came from (`FieldReader`).
 * Validates and normalizes one raw answer to the string
 * `appointment_answers.value` stores.
 */
final class Field
{
    /** appointment_answers.value is TEXT; this keeps one answer reasonable, like customer_note. */
    public const MAX_ANSWER_LENGTH = 2000;

    /**
     * @param list<string> $options the choices of a select field; ignored otherwise.
     */
    public function __construct(
        public readonly string $key,
        public readonly FieldType $type,
        public readonly string $label,
        public readonly bool $required,
        public readonly array $options,
        public readonly ?ShowIf $showIf,
        public readonly int $sort,
    ) {
        if ('' === $key || '' === $label) {
            throw new InvalidValue('invalid_field', 'A field needs a key and a label.');
        }
        if (FieldType::Select === $type && [] === $options) {
            throw new InvalidValue('invalid_field', 'A select field needs at least one option.');
        }
    }

    /**
     * @throws InvalidValue invalid_answer when $value does not fit the type.
     */
    public function validate(mixed $value): string
    {
        return match ($this->type) {
            FieldType::Checkbox => $this->checkbox($value),
            FieldType::Number => $this->number($value),
            FieldType::Select => $this->select($value),
            FieldType::Text, FieldType::Textarea => $this->text($value),
        };
    }

    /**
     * Unlike other types, a checkbox is always "answered" once it is sent
     * at all, even as false; required only refuses a missing checkbox, not
     * an unchecked one, EXCEPT here: a required checkbox is a consent gate
     * (e.g. "I agree to the cancellation policy"), so false fails the same
     * way as never answering.
     */
    private function checkbox(mixed $value): string
    {
        if (!\is_bool($value)) {
            throw $this->invalidAnswer();
        }
        if ($this->required && false === $value) {
            throw $this->requiredAnswer();
        }

        return $value ? '1' : '0';
    }

    private function number(mixed $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        $normalized = \is_string($value) ? PersianDigits::toLatin($value) : null;
        if (null === $normalized || !\is_numeric($normalized)) {
            throw $this->invalidAnswer();
        }

        return $normalized;
    }

    private function select(mixed $value): string
    {
        if (!\is_string($value) || !\in_array($value, $this->options, true)) {
            throw $this->invalidAnswer();
        }

        return $value;
    }

    private function text(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            throw $this->invalidAnswer();
        }
        $text = \trim((string) $value);
        if ('' === $text || \strlen($text) > self::MAX_ANSWER_LENGTH) {
            throw $this->invalidAnswer();
        }

        return $text;
    }

    private function invalidAnswer(): InvalidValue
    {
        return new InvalidValue('invalid_answer', "The answer for \"{$this->label}\" is not valid.");
    }
}
