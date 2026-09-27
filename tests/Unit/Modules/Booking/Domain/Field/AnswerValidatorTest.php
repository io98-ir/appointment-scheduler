<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Domain\Field;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Domain\Field\AnswerValidator;
use Vaqtyar\Modules\Booking\Domain\Field\Field;
use Vaqtyar\Modules\Booking\Domain\Field\FieldType;
use Vaqtyar\Modules\Booking\Domain\Field\ShowIf;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * Custom field answers at booking time (T2.6): a hidden field (its show_if
 * does not match the raw answers) is never required and never stored; a
 * blank optional field is skipped; everything else is validated by its
 * Field and returned as a string, keyed by field_key.
 */
final class AnswerValidatorTest extends TestCase
{
    public function testAnAnsweredFieldIsValidatedAndKept(): void
    {
        $fields = [new Field('note', FieldType::Text, 'Note', false, [], null, 0)];

        self::assertSame(['note' => 'window seat'], AnswerValidator::validate($fields, ['note' => 'window seat']));
    }

    public function testAMissingOptionalFieldIsSkipped(): void
    {
        $fields = [new Field('note', FieldType::Text, 'Note', false, [], null, 0)];

        self::assertSame([], AnswerValidator::validate($fields, []));
    }

    public function testAMissingRequiredFieldFails(): void
    {
        $fields = [new Field('note', FieldType::Text, 'Note', true, [], null, 0)];

        try {
            AnswerValidator::validate($fields, []);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('answer_required', $e->errorCode);
        }
    }

    public function testABlankAnswerToARequiredFieldFails(): void
    {
        $fields = [new Field('note', FieldType::Text, 'Note', true, [], null, 0)];

        try {
            AnswerValidator::validate($fields, ['note' => '']);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('answer_required', $e->errorCode);
        }
    }

    public function testAHiddenFieldIsNeverRequired(): void
    {
        $fields = [
            new Field('has_car', FieldType::Checkbox, 'Has a car?', false, [], null, 0),
            new Field('plate', FieldType::Text, 'Plate', true, [], new ShowIf('has_car', '1'), 1),
        ];

        self::assertSame(
            ['has_car' => '0'],
            AnswerValidator::validate($fields, ['has_car' => false])
        );
    }

    public function testAVisibleConditionalFieldIsRequiredAndKept(): void
    {
        $fields = [
            new Field('has_car', FieldType::Checkbox, 'Has a car?', false, [], null, 0),
            new Field('plate', FieldType::Text, 'Plate', true, [], new ShowIf('has_car', '1'), 1),
        ];

        self::assertSame(
            ['has_car' => '1', 'plate' => '12A345'],
            AnswerValidator::validate($fields, ['has_car' => true, 'plate' => '12A345'])
        );
    }

    public function testAnInvalidAnswerFailsWithTheFieldsErrorCode(): void
    {
        $fields = [new Field('size', FieldType::Select, 'Size', true, ['s', 'm', 'l'], null, 0)];

        try {
            AnswerValidator::validate($fields, ['size' => 'xl']);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('invalid_answer', $e->errorCode);
        }
    }

    public function testFieldsAreCheckedInOrderSoAConditionCanUseAnEarlierAnswer(): void
    {
        $fields = [
            new Field('country', FieldType::Select, 'Country', true, ['ir', 'other'], null, 0),
            new Field('province', FieldType::Text, 'Province', true, [], new ShowIf('country', 'ir'), 1),
        ];

        self::assertSame(
            ['country' => 'ir', 'province' => 'Tehran'],
            AnswerValidator::validate($fields, ['country' => 'ir', 'province' => 'Tehran'])
        );
    }
}
