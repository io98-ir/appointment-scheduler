<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * The secret that proves a client placed a hold. Only its hash is stored,
 * so a leaked database does not let anyone confirm someone else's hold.
 */
final class HoldToken
{
    private function __construct(public readonly string $value)
    {
    }

    public static function generate(): self
    {
        return new self(\rtrim(\strtr(\base64_encode(\random_bytes(32)), '+/', '-_'), '='));
    }

    public static function fromString(string $value): self
    {
        if (1 !== \preg_match('/^[A-Za-z0-9_-]{43}$/D', $value)) {
            throw new InvalidValue('invalid_hold_token', 'The hold token is not valid.');
        }

        return new self($value);
    }

    /**
     * @return non-empty-string 64 hex characters.
     */
    public function hash(): string
    {
        return \hash('sha256', $this->value);
    }
}
