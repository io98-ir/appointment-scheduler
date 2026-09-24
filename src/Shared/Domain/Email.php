<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * An email address, trimmed, with a lowercase domain.
 */
final class Email
{
    /** RFC 5321 path limit. */
    private const MAX_LENGTH = 254;

    private function __construct(public readonly string $value)
    {
    }

    public static function fromInput(string $input): self
    {
        $email = \trim($input);
        if (\strlen($email) > self::MAX_LENGTH || false === \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new InvalidValue('invalid_email', 'Not a valid email address.');
        }
        $at = (int) \strrpos($email, '@');

        return new self(\substr($email, 0, $at) . '@' . \strtolower(\substr($email, $at + 1)));
    }

    /**
     * Case-insensitive: mail servers treat the local part that way in practice,
     * and customer matching must too.
     */
    public function equals(self $other): bool
    {
        return \strtolower($this->value) === \strtolower($other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
