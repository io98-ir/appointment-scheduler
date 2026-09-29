<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Application;

use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\PersianDigits;
use Vaqtyar\Shared\Domain\PhoneNumber;

/**
 * Proves a phone number belongs to the person at the widget (T4.3): a
 * six-digit code sent to it, which lives 5 minutes and allows 5 guesses,
 * with at most 3 codes to one number in 10 minutes and one a minute. Whether
 * the number belongs to a customer is never revealed.
 */
final class OtpService
{
    public const CODE_LENGTH = 6;
    public const TTL_SECONDS = 300;
    public const MAX_ATTEMPTS = 5;
    public const MAX_CODES = 3;
    public const WINDOW_SECONDS = 600;
    public const RESEND_SECONDS = 60;

    /** Codes older than this are forgotten. */
    private const KEEP_SECONDS = 86_400;

    public function __construct(
        private readonly OtpStore $store,
        private readonly OtpSender $sender,
        private readonly PhoneSessions $sessions,
        private readonly Clock $clock,
        private readonly string $key,
    ) {
    }

    /**
     * Sends a code, unless the number asked too often.
     *
     * @return int 0 once a code was sent; else the seconds to wait.
     */
    public function request(PhoneNumber $phone): int
    {
        $now = $this->clock->now()->getTimestamp();
        $this->store->purgeBefore($now - self::KEEP_SECONDS);
        $last = $this->store->lastCreatedAt($phone->e164);
        if (null !== $last && $now - $last < self::RESEND_SECONDS) {
            return self::RESEND_SECONDS - ($now - $last);
        }
        if ($this->store->countSince($phone->e164, $now - self::WINDOW_SECONDS) >= self::MAX_CODES) {
            return self::WINDOW_SECONDS;
        }
        $code = \str_pad((string) \random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', \STR_PAD_LEFT);
        $this->store->add($phone->e164, $this->hash($phone->e164, $code), $now + self::TTL_SECONDS, $now);
        $this->sender->send($phone->e164, $code);

        return 0;
    }

    /**
     * @return array{token: string, expires_at: int}|null the phone session, or null for a wrong, expired
     *     or used code (which of them is not said).
     */
    public function verify(PhoneNumber $phone, string $code): ?array
    {
        $now = $this->clock->now()->getTimestamp();
        $code = PersianDigits::toLatin(\trim($code));
        $stored = $this->store->latest($phone->e164, $now);
        if (null === $stored || $stored->attempts >= self::MAX_ATTEMPTS) {
            return null;
        }
        // Counted before the comparison, so parallel guesses cannot beat the limit.
        $this->store->recordAttempt($stored->id);
        if (1 !== \preg_match('/^\d{' . self::CODE_LENGTH . '}$/D', $code)
            || !\hash_equals($stored->hash, $this->hash($phone->e164, $code))
            || !$this->store->consume($stored->id, $now)
        ) {
            return null;
        }

        return $this->sessions->issue($phone->e164, $now);
    }

    private function hash(string $phone, string $code): string
    {
        return \hash_hmac('sha256', 'otp|' . $phone . '|' . $code, $this->key);
    }
}
