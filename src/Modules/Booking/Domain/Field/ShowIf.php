<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Field;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * A field's simple condition (data-model §2, `fields.show_if`): it is shown
 * only when an earlier visible field's validated answer equals a fixed
 * value, e.g. '1' or '0' for a checkbox. No AND/OR trees, by design
 * (roadmap T2.6, "شرط ساده").
 */
final class ShowIf
{
    public function __construct(public readonly string $fieldKey, public readonly string $equals)
    {
        if ('' === $fieldKey) {
            throw new InvalidValue('invalid_field', 'A condition needs the other field\'s key.');
        }
    }

    /**
     * @param array<string, string> $answers validated so far, by field_key.
     */
    public function matches(array $answers): bool
    {
        return ($answers[$this->fieldKey] ?? null) === $this->equals;
    }
}
