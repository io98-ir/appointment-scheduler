<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

use DateTimeImmutable;

/**
 * A ULID (https://github.com/ulid/spec): 48-bit millisecond time and 80 random
 * bits, as 26 Crockford base32 characters that sort by time. Public id of
 * appointments and customers (ADR-013).
 *
 * Building one is pure: the caller supplies the time and the random bytes,
 * so the Domain stays free of clocks and randomness.
 */
final class Ulid
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const MAX_MILLISECONDS = 281_474_976_710_655; // 2^48 − 1
    private const RANDOM_BYTES = 10;

    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $ulid): self
    {
        $ulid = \strtoupper($ulid);
        // The first character carries only 3 bits: above 7 the value exceeds 128 bits.
        if (1 !== \preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $ulid)) {
            throw new InvalidValue('invalid_ulid', 'Not a valid ULID.');
        }

        return new self($ulid);
    }

    /**
     * @param string $random Exactly 10 random bytes, e.g. from random_bytes(10).
     */
    public static function fromParts(DateTimeImmutable $time, string $random): self
    {
        $milliseconds = (int) $time->format('Uv');
        if ($time->getTimestamp() < 0 || $milliseconds > self::MAX_MILLISECONDS) {
            throw new InvalidValue('invalid_ulid', 'The time is outside the range a ULID can hold.');
        }
        if (self::RANDOM_BYTES !== \strlen($random)) {
            throw new InvalidValue('invalid_ulid', 'A ULID needs exactly 10 random bytes.');
        }

        $time = '';
        for ($i = 0; $i < 10; ++$i) {
            $time = self::ALPHABET[$milliseconds % 32] . $time;
            $milliseconds = \intdiv($milliseconds, 32);
        }

        // 80 bits → 16 characters of 5 bits.
        $randomness = '';
        $buffer = 0;
        $bits = 0;
        foreach (\str_split($random) as $byte) {
            $buffer = ($buffer << 8) | \ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $randomness .= self::ALPHABET[($buffer >> $bits) & 31];
            }
            $buffer &= (1 << $bits) - 1;
        }

        return new self($time . $randomness);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
