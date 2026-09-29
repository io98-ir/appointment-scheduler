<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Application;

/**
 * The site's own captcha (T4.3): a sum of two digits, with a signed token of
 * the answer and an expiry, so nothing is stored and no third party is
 * called. It only slows scripts down, before the per-number and per-client
 * limits do the real work.
 */
final class Captcha
{
    public const TTL_SECONDS = 120;

    public function __construct(private readonly string $key)
    {
    }

    /**
     * @return array{a: int, b: int, token: string}
     */
    public function issue(int $now): array
    {
        $a = \random_int(1, 9);
        $b = \random_int(1, 9);
        $expires = $now + self::TTL_SECONDS;

        return ['a' => $a, 'b' => $b, 'token' => $expires . '.' . $this->sign($expires, $a + $b)];
    }

    public function verify(string $token, string $answer, int $now): bool
    {
        $parts = \explode('.', $token);
        $answer = \trim($answer);
        if (2 !== \count($parts) || !\ctype_digit($parts[0]) || (int) $parts[0] < $now || !\ctype_digit($answer)) {
            return false;
        }

        return \hash_equals($this->sign((int) $parts[0], (int) $answer), $parts[1]);
    }

    private function sign(int $expires, int $answer): string
    {
        return \hash_hmac('sha256', 'captcha|' . $expires . '|' . $answer, $this->key);
    }
}
