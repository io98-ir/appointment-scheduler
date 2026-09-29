<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Domain;

/**
 * The Iranian mobile number the SMS providers take, in the two forms they
 * ask for. Anything else (a foreign number, a landline) is not reachable
 * through them.
 */
final class SmsNumber
{
    /**
     * @param string $e164 +989121234567
     * @return ?string 09121234567, or null when it is not an Iranian mobile number.
     */
    public static function local(string $e164): ?string
    {
        return 1 === \preg_match('/^\+989\d{9}$/D', $e164) ? '0' . \substr($e164, 3) : null;
    }

    /**
     * @param string $local 09121234567
     */
    public static function international(string $local): string
    {
        return '+98' . \substr($local, 1);
    }
}
