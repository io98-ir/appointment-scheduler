<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Application;

/**
 * A proof that a phone number was verified with its one-time code (T4.3):
 * a signed token of the number and an expiry, so nothing is stored. The
 * booking and the customer panel (T4.4) accept it in place of the code.
 */
final class PhoneSessions
{
    /** Half an hour: enough to fill a booking form, short for a stolen token. */
    public const TTL_SECONDS = 1800;

    public function __construct(private readonly string $key)
    {
    }

    /**
     * @param string $phone E.164.
     * @return array{token: string, expires_at: int}
     */
    public function issue(string $phone, int $now): array
    {
        $expires = $now + self::TTL_SECONDS;
        $payload = self::encode($phone . '|' . $expires);

        return ['token' => $payload . '.' . $this->sign($payload), 'expires_at' => $expires];
    }

    /**
     * The verified number (E.164) of a token that is genuine and unexpired.
     */
    public function phoneOf(?string $token, int $now): ?string
    {
        $parts = null === $token ? [] : \explode('.', $token);
        if (2 !== \count($parts) || !\hash_equals($this->sign($parts[0]), $parts[1])) {
            return null;
        }
        $decoded = \base64_decode(\strtr($parts[0], '-_', '+/'), true);
        $fields = false === $decoded ? [] : \explode('|', $decoded);
        if (2 !== \count($fields) || !\ctype_digit($fields[1]) || (int) $fields[1] < $now) {
            return null;
        }

        return $fields[0];
    }

    private function sign(string $payload): string
    {
        return \hash_hmac('sha256', 'phone-session|' . $payload, $this->key);
    }

    private static function encode(string $value): string
    {
        return \rtrim(\strtr(\base64_encode($value), '+/', '-_'), '=');
    }
}
