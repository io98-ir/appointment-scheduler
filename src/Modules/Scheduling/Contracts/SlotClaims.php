<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Contracts;

/**
 * What a hold asks of scheduling (booking-engine §3, ADR-004): first what it
 * may take, so it locks that before reading; then, under the locks, what a
 * booking at the start takes, from the database and never from the cache.
 *
 * Both check the query as availability does: NotFound for an unknown
 * variant or location, InvalidValue for a staff member, extra or party size
 * the variant does not take.
 */
interface SlotClaims
{
    /**
     * Everyone and everything a booking at $start (UTC seconds) could take,
     * over the longest time it could take them.
     */
    public function scope(AvailabilityQuery $query, int $start): ClaimScope;

    /**
     * Null when $start is not an offered start or nobody fits it any more.
     */
    public function claim(AvailabilityQuery $query, int $start): ?Claim;
}
