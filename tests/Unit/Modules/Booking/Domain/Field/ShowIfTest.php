<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Domain\Field;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Domain\Field\ShowIf;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * A field's simple condition (data-model §2, `fields.show_if`): shown only
 * when another field's raw answer equals a fixed value.
 */
final class ShowIfTest extends TestCase
{
    public function testItNeedsAFieldKey(): void
    {
        try {
            new ShowIf('', 'yes');
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('invalid_field', $e->errorCode);
        }
    }

    public function testItMatchesWhenTheOtherAnswerEqualsTheValue(): void
    {
        $showIf = new ShowIf('has_car', 'yes');

        self::assertTrue($showIf->matches(['has_car' => 'yes']));
    }

    public function testItDoesNotMatchADifferentAnswer(): void
    {
        $showIf = new ShowIf('has_car', 'yes');

        self::assertFalse($showIf->matches(['has_car' => 'no']));
    }

    public function testItDoesNotMatchWhenTheOtherFieldWasNotAnswered(): void
    {
        $showIf = new ShowIf('has_car', 'yes');

        self::assertFalse($showIf->matches([]));
    }

    public function testItComparesAsAStringSoABooleanOrANumberCanMatch(): void
    {
        $showIf = new ShowIf('agrees', '1');

        self::assertTrue($showIf->matches(['agrees' => true]));
        self::assertTrue($showIf->matches(['agrees' => 1]));
    }

    public function testANonScalarAnswerNeverMatches(): void
    {
        $showIf = new ShowIf('extras', '1');

        self::assertFalse($showIf->matches(['extras' => ['a', 'b']]));
    }
}
