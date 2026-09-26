<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * The display name of a catalog item: one trimmed line. Names reach SMS
 * texts and email subjects, so control characters are refused.
 */
final class Name
{
    /** Characters, the VARCHAR(191) columns (utf8mb4 index limit). */
    public const MAX_LENGTH = 191;

    private function __construct(public readonly string $value)
    {
    }

    public static function fromInput(string $input): self
    {
        $name = \trim($input);
        if (
            '' === $name
            || 1 === \preg_match('/\p{Cc}/u', $name)
            || false === \mb_check_encoding($name, 'UTF-8')
            || \mb_strlen($name) > self::MAX_LENGTH
        ) {
            throw new InvalidValue('invalid_name', 'A name must be one line of 1 to 191 characters.');
        }

        return new self($name);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
