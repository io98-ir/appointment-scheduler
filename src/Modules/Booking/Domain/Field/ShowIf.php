<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Field;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * A field's simple condition (data-model §2, `fields.show_if`): it is shown
 * only when another field's raw answer equals a fixed value. No AND/OR
 * trees, by design (roadmap T2.6, "شرط ساده").
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
     * @param array<string, mixed> $answers raw, exactly as the client sent them.
     */
    public function matches(array $answers): bool
    {
        $value = $answers[$this->fieldKey] ?? null;

        return \is_scalar($value) && (string) $value === $this->equals;
    }
}
