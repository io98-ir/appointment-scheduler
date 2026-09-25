<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Rest;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Shared\Domain\Clock;

/**
 * Fixed-window counters in the rate_limits table (data-model §2).
 *
 * A table and not transients: the count must be atomic, and a transient
 * read-then-write lets concurrent requests all pass. Call it outside a
 * transaction, so its row lock lasts one statement.
 */
final class RateLimiter
{
    private const TABLE = 'rate_limits';

    /** Expired rows removed at a time; the next new window takes the rest. */
    private const PRUNE_BATCH = 100;

    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Counts one attempt against the key's budget; refused attempts count too.
     *
     * @param string $key What is limited and for whom, e.g. "otp:phone:+98912…".
     *     Stored only as a keyed hash, so it may hold personal data.
     * @return int 0 when the attempt is allowed, otherwise the seconds until
     *     the window resets (for Retry-After).
     * @phpstan-impure
     */
    public function attempt(string $key, RateLimit $rule): int
    {
        $now = $this->clock->now()->getTimestamp();
        $windowStart = \intdiv($now, $rule->windowSeconds) * $rule->windowSeconds;
        $expiresAt = $windowStart + $rule->windowSeconds;
        // The window length is part of the bucket: aligned windows of two
        // lengths start at different times and must not share a row.
        $bucket = \hash_hmac('sha256', $rule->windowSeconds . ':' . $key, \wp_salt('nonce'));

        // One atomic statement. LAST_INSERT_ID(expr) hands the new count back
        // with the statement's own result, so a concurrent attempt cannot be read by mistake.
        $affected = $this->db->execute(
            'INSERT INTO %i (bucket, window_start, expires_at, hits) VALUES (%s, %s, %s, 1)'
            . ' ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(hits + 1)',
            Tables::name(self::TABLE),
            $bucket,
            \gmdate('Y-m-d H:i:s', $windowStart),
            \gmdate('Y-m-d H:i:s', $expiresAt)
        );
        // MySQL reports 1 affected row for an insert and 2 for an update.
        $hits = 1 === $affected ? 1 : $this->db->lastInsertId();

        if (1 === $hits) {
            $this->prune($now);
        }

        return $hits > $rule->limit ? $expiresAt - $now : 0;
    }

    /**
     * Runs when a bucket starts a new window, so the table stays about as
     * large as the number of clients active right now.
     */
    private function prune(int $now): void
    {
        $this->db->execute(
            'DELETE FROM %i WHERE expires_at <= %s LIMIT ' . self::PRUNE_BATCH,
            Tables::name(self::TABLE),
            \gmdate('Y-m-d H:i:s', $now)
        );
    }
}
