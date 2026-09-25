<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * A calendar color as a lowercase hex triplet ("#1a2b3c"). Nothing else is
 * accepted, since the value is written into inline styles.
 */
final class Color
{
    private function __construct(public readonly string $value)
    {
    }

    public static function fromInput(string $input): self
    {
        $color = \strtolower(\trim($input));
        if (1 !== \preg_match('/^#[0-9a-f]{6}$/D', $color)) {
            throw new InvalidValue('invalid_color', 'A color must be a hex triplet such as #1a2b3c.');
        }

        return new self($color);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
