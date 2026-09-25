<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * A machine key an admin chooses, such as a resource group ("room") or a
 * holiday calendar ("ir"): lowercase letters and digits, with single "-" or
 * "_" between them.
 */
final class Slug
{
    public const MAX_LENGTH = 64;

    private function __construct(public readonly string $value)
    {
    }

    public static function fromInput(string $input): self
    {
        $slug = \strtolower(\trim($input));
        if (\strlen($slug) > self::MAX_LENGTH || 1 !== \preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/D', $slug)) {
            throw new InvalidValue('invalid_slug', 'A key must be lowercase letters and digits, joined by - or _.');
        }

        return new self($slug);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
