<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Hold;

/**
 * The holds and their occupancies. Every method but find() runs inside the
 * caller's transaction.
 */
interface HoldRepository
{
    /**
     * Writes the hold and one occupancy per lock key.
     *
     * @param non-empty-string $tokenHash
     * @return int The hold id.
     */
    public function add(Hold $hold, string $tokenHash): int;

    /**
     * An expired hold too; null when there is none.
     *
     * @param non-empty-string $tokenHash
     */
    public function find(string $tokenHash, bool $forUpdate = false): ?StoredHold;

    /**
     * Moves the expiry of the hold and of its occupancies.
     */
    public function extend(int $id, int $expiresAt): void;

    /**
     * Deletes up to $limit holds expired by $now, with their occupancies,
     * and the day locks of days before $now's.
     *
     * @return int How many holds.
     */
    public function purgeExpired(int $now, int $limit): int;
}
