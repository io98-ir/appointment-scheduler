<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

/**
 * A hold as read back (HoldRepository::find()); times are UTC seconds.
 */
final class StoredHold
{
    /**
     * @param list<string> $lockKeys of its occupancies.
     * @param int $from the earliest start of its occupancies.
     * @param int $to the latest end.
     */
    public function __construct(
        public readonly int $id,
        public readonly array $lockKeys,
        public readonly int $from,
        public readonly int $to,
        public readonly int $createdAt,
        public readonly int $expiresAt,
    ) {
    }
}
