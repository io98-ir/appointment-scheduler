<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Domain\Field;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Domain\Field\Field;
use Vaqtyar\Modules\Booking\Domain\Field\FieldType;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * A field definition (data-model §2, `fields`) validates and normalizes one
 * answer to a string, the shape `appointment_answers.value` stores.
 */
final class FieldTest extends TestCase
{
    public function testAKeyOrALabelIsRequired(): void
    {
        try {
            self::field(key: '');
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('invalid_field', $e->errorCode);
        }
        try {
            self::field(label: '');
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('invalid_field', $e->errorCode);
        }
    }

    public function testASelectFieldNeedsAtLeastOneOption(): void
    {
        try {
            self::field(type: FieldType::Select, options: []);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('invalid_field', $e->errorCode);
        }
    }

    public function testTextTrimsAndAcceptsAnyScalar(): void
    {
        self::assertSame('window seat', self::field()->validate('  window seat  '));
        self::assertSame('2', self::field()->validate(2));
    }

    public function testABlankTextAfterTrimIsNotValid(): void
    {
        self::assertInvalidAnswer(static fn () => self::field()->validate('   '));
    }

    public function testTextRejectsAnArray(): void
    {
        self::assertInvalidAnswer(static fn () => self::field()->validate(['not', 'a', 'string']));
    }

    public function testTextRejectsAnAnswerLongerThanTheLimit(): void
    {
        self::assertInvalidAnswer(
            static fn () => self::field()->validate(\str_repeat('a', Field::MAX_ANSWER_LENGTH + 1))
        );
    }

    public function testNumberAcceptsIntFloatAndNumericStrings(): void
    {
        $field = self::field(type: FieldType::Number);

        self::assertSame('3', $field->validate(3));
        self::assertSame('3.5', $field->validate(3.5));
        self::assertSame('4', $field->validate('4'));
    }

    public function testNumberRejectsANonNumericString(): void
    {
        self::assertInvalidAnswer(static fn () => self::field(type: FieldType::Number)->validate('four'));
    }

    public function testSelectAcceptsOnlyOneOfItsOptions(): void
    {
        $field = self::field(type: FieldType::Select, options: ['red', 'blue']);

        self::assertSame('blue', $field->validate('blue'));
    }

    public function testSelectRejectsAValueOutsideItsOptions(): void
    {
        self::assertInvalidAnswer(
            static fn () => self::field(type: FieldType::Select, options: ['red', 'blue'])->validate('green')
        );
    }

    public function testCheckboxAcceptsOnlyBooleans(): void
    {
        $field = self::field(type: FieldType::Checkbox);

        self::assertSame('1', $field->validate(true));
        self::assertSame('0', $field->validate(false));
    }

    public function testCheckboxRejectsAStringOrANumber(): void
    {
        self::assertInvalidAnswer(static fn () => self::field(type: FieldType::Checkbox)->validate('1'));
    }

    public function testARequiredCheckboxMustBeChecked(): void
    {
        $field = self::field(type: FieldType::Checkbox, required: true);

        self::assertSame('1', $field->validate(true));
        try {
            $field->validate(false);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('answer_required', $e->errorCode);
        }
    }

    public function testNumberNormalizesPersianDigitsAndSpaces(): void
    {
        self::assertSame('12', self::field(type: FieldType::Number)->validate(' ۱۲ '));
    }

    public function testNumberRejectsAnOverlongOrInfiniteValue(): void
    {
        $field = self::field(type: FieldType::Number);

        self::assertInvalidAnswer(static fn () => $field->validate('1' . \str_repeat('0', Field::MAX_ANSWER_LENGTH)));
        self::assertInvalidAnswer(static fn () => $field->validate(\INF));
        self::assertInvalidAnswer(static fn () => $field->validate(\NAN));
    }

    public function testTextRejectsInvalidUtf8(): void
    {
        self::assertInvalidAnswer(static fn () => self::field()->validate("\xFF\xFE"));
    }

    public function testTheLengthLimitCountsCharactersNotBytes(): void
    {
        self::assertSame(
            \str_repeat('س', Field::MAX_ANSWER_LENGTH),
            self::field()->validate(\str_repeat('س', Field::MAX_ANSWER_LENGTH))
        );
    }

    /**
     * @param \Closure(): mixed $call
     */
    private static function assertInvalidAnswer(\Closure $call): void
    {
        try {
            $call();
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('invalid_answer', $e->errorCode);
        }
    }

    /**
     * @param list<string> $options
     */
    private static function field(
        string $key = 'note',
        FieldType $type = FieldType::Text,
        string $label = 'Note',
        bool $required = false,
        array $options = [],
    ): Field {
        return new Field($key, $type, $label, $required, $options, null, 0);
    }
}
