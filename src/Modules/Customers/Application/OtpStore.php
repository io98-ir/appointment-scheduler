<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Application;

/**
 * The one-time codes sent to phone numbers (T4.3). Only a keyed hash of a
 * code is kept, never the code itself.
 */
interface OtpStore
{
    /**
     * How many codes went to this number since a time (UTC seconds).
     */
    public function countSince(string $phone, int $since): int;

    /**
     * When the last code to this number was made; null if none is kept.
     */
    public function lastCreatedAt(string $phone): ?int;

    public function add(string $phone, string $hash, int $expiresAt, int $now): void;

    /**
     * The newest code of the number that is not used up and has not expired.
     */
    public function latest(string $phone, int $now): ?StoredOtp;

    /**
     * Takes one guess of the code, in one statement: false when it has had $max
     * already. The count is decided by the write, since a read before it
     * would let parallel guesses all see room.
     */
    public function recordAttempt(int $id, int $max): bool;

    /**
     * Uses the code up, once: false when another request already did.
     */
    public function consume(int $id, int $now): bool;

    /**
     * Forgets codes made before a time (UTC seconds).
     */
    public function purgeBefore(int $before): void;
}
