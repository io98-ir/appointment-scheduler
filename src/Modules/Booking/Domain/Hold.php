<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain;

use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * A slot kept for a customer while they finish booking (booking-engine §3).
 * Its occupancies, one per staff member and resource unit over [from, to),
 * take the time until expiresAt; nothing has to delete it for the time to
 * free up. Times are UTC seconds.
 */
final class Hold
{
    /** How long a new hold, and each extension, lasts. */
    public const TTL_SECONDS = 600;

    /** No extension keeps a hold past this long after it was placed (booking-engine §3). */
    public const MAX_LIFETIME_SECONDS = 1200;

    /** The occupancy reader and the calendar query rely on this bound (WpdbOccupancyReader, WpdbAppointmentQuery). */
    public const MAX_SPAN_SECONDS = 7 * 86_400;

    /**
     * @param int $from the start minus the buffer before.
     * @param int $to the end plus the buffer after.
     * @param list<int> $extraIds an id once per unit.
     * @param list<int> $resourceIds
     * @param PriceQuote $quote the price when placed, kept with the booking.
     */
    public function __construct(
        public readonly int $locationId,
        public readonly int $variantId,
        public readonly int $staffId,
        public readonly int $start,
        public readonly int $end,
        public readonly int $from,
        public readonly int $to,
        public readonly int $partySize,
        public readonly array $extraIds,
        public readonly array $resourceIds,
        public readonly int $createdAt,
        public readonly int $expiresAt,
        public readonly PriceQuote $quote,
    ) {
        if (!($from <= $start && $start < $end && $end <= $to)) {
            throw new InvalidValue('invalid_interval', 'A hold must end after it starts, inside its buffers.');
        }
        if ($to - $from > self::MAX_SPAN_SECONDS) {
            throw new InvalidValue('booking_too_long', 'A booking takes at most seven days.');
        }
        if ($partySize < 1) {
            throw new InvalidValue('invalid_party_size', 'A party is at least one person.');
        }
        if ($expiresAt <= $createdAt || $expiresAt > $createdAt + self::MAX_LIFETIME_SECONDS) {
            throw new InvalidValue('invalid_expiry', 'A hold expires within its lifetime.');
        }
    }

    /**
     * @return list<string> The keys of the staff member and every unit, sorted.
     */
    public function lockKeys(): array
    {
        return LockKey::sorted([$this->staffId], $this->resourceIds);
    }

    /**
     * When an extension at $now would end: a full TTL, but not past the lifetime.
     */
    public static function extendedExpiry(int $createdAt, int $now): int
    {
        return \min($now + self::TTL_SECONDS, $createdAt + self::MAX_LIFETIME_SECONDS);
    }
}
