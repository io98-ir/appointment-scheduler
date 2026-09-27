<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Appointment;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * The short code a customer reads out or types to find their booking
 * (ADR-013): 8 Crockford base32 characters, with no I, L, O or U to misread.
 */
final class TrackingCode
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const LENGTH = 8;

    private function __construct(public readonly string $value)
    {
    }

    public static function generate(): self
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $code .= self::ALPHABET[\random_int(0, 31)];
        }

        return new self($code);
    }

    public static function fromString(string $value): self
    {
        $value = \strtoupper($value);
        if (1 !== \preg_match('/^[0-9A-HJKMNP-TV-Z]{8}$/D', $value)) {
            throw new InvalidValue('invalid_tracking_code', 'The tracking code is not valid.');
        }

        return new self($value);
    }
}
