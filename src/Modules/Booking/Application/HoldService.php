<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Hold;
use Vaqtyar\Modules\Booking\Domain\HoldToken;
use Vaqtyar\Modules\Booking\Domain\LockKey;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Modules\Scheduling\Contracts\Claim;
use Vaqtyar\Modules\Scheduling\Contracts\SlotClaims;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\TransactionRunner;

/**
 * Places, extends and expires holds (booking-engine §3, ADR-004). No double
 * booking: in one transaction, the calendars a booking could take are locked
 * in sorted order first, then the slot is checked again from the database,
 * then the hold and its occupancies are written.
 *
 * Anyone may hold a slot, as the booking widget does before any login; the
 * REST layer asks for the site's nonce and rate-limits.
 */
final class HoldService
{
    /** Holds deleted per purge run. */
    public const PURGE_BATCH = 500;

    /**
     * @param \Closure(): void $changed Tells availability the occupancies
     *     changed; called after the commit.
     */
    public function __construct(
        private readonly SlotClaims $slots,
        private readonly HoldPricing $pricing,
        private readonly ResourceLocker $locker,
        private readonly HoldRepository $holds,
        private readonly TransactionRunner $transaction,
        private readonly Clock $clock,
        private readonly \Closure $changed,
    ) {
    }

    /**
     * @param int $start UTC seconds.
     * @param ?string $couponCode as the customer typed it.
     * @throws Conflict slot_taken when the start is not free any more, or
     *     was never offered.
     */
    public function place(AvailabilityQuery $query, int $start, ?string $couponCode = null): PlacedHold
    {
        // Outside the transaction: a read before the locks would fix the
        // snapshot the re-check reads (REPEATABLE READ).
        $scope = $this->slots->scope($query, $start);
        $keys = LockKey::sorted($scope->staffIds, $scope->resourceIds);
        $this->locker->prepare($keys, $scope->from, $scope->to);
        $token = HoldToken::generate();

        $work = function () use ($query, $start, $scope, $keys, $token, $couponCode): PlacedHold {
            $this->locker->lock($keys, $scope->from, $scope->to);
            $claim = $this->slots->claim($query, $start);
            if (null === $claim || !self::within($claim, $keys, $scope->from, $scope->to)) {
                throw new Conflict('slot_taken', 'The time is no longer free.');
            }
            $now = $this->clock->now()->getTimestamp();
            $hold = new Hold(
                $query->locationId,
                $query->variantId,
                $claim->staffId,
                $claim->start,
                $claim->end,
                $claim->from,
                $claim->to,
                $query->partySize,
                $query->extraIds,
                $claim->resourceIds,
                $now,
                $now + Hold::TTL_SECONDS,
                $this->pricing->quote($query, $claim, $now, $couponCode)
            );
            $id = $this->holds->add($hold, $token->hash());

            return new PlacedHold($id, $token->value, $hold);
        };
        $placed = $this->transaction->run($work);
        ($this->changed)();

        return $placed;
    }

    /**
     * Keeps a live hold for another TTL, e.g. while the customer pays, but
     * never past its lifetime.
     *
     * @return int The new expiry, UTC seconds.
     * @throws NotFound hold_not_found when the token is unknown or expired.
     */
    public function extend(HoldToken $token): int
    {
        $found = $this->holds->find($token->hash());
        if (null === $found) {
            throw self::notFound();
        }

        $expiresAt = $this->transaction->run(function () use ($found, $token): int {
            // Under the locks: a hold about to expire must not come back to
            // life after another booking took its time.
            $this->locker->lock($found->lockKeys, $found->from, $found->to);
            $hold = $this->holds->find($token->hash(), true);
            $now = $this->clock->now()->getTimestamp();
            if (null === $hold || $hold->expiresAt <= $now) {
                throw self::notFound();
            }
            $expiresAt = Hold::extendedExpiry($hold->createdAt, $now);
            if ($expiresAt > $hold->expiresAt) {
                $this->holds->extend($hold->id, $expiresAt);
            }

            return \max($expiresAt, $hold->expiresAt);
        });
        ($this->changed)();

        return $expiresAt;
    }

    /**
     * Deletes expired holds. Nothing depends on it for correctness, since
     * readers skip expired holds; it keeps the tables small.
     *
     * @return int How many holds.
     */
    public function purgeExpired(): int
    {
        $now = $this->clock->now()->getTimestamp();
        $purged = $this->transaction->run(fn (): int => $this->holds->purgeExpired($now, self::PURGE_BATCH));
        if ($purged > 0) {
            ($this->changed)();
        }

        return $purged;
    }

    /**
     * Whether the claim takes only calendars that were locked, over days
     * that were locked: the catalog may have changed between the two reads.
     *
     * @param list<string> $keys
     */
    private static function within(Claim $claim, array $keys, int $from, int $to): bool
    {
        $taken = LockKey::sorted([$claim->staffId], $claim->resourceIds);

        return [] === \array_diff($taken, $keys) && $claim->from >= $from && $claim->to <= $to;
    }

    private static function notFound(): NotFound
    {
        return new NotFound('hold_not_found', 'The hold does not exist or has expired.');
    }
}
