<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\Slug;

/**
 * A room, chair or device. Services ask for one of a group ("room"), not a
 * given resource, so any free member of the group can serve (booking-engine §1).
 */
final class BookableResource
{
    use GuardsStoredNumbers;

    public const MAX_CAPACITY = 1000;

    /**
     * @param ?int $id null until stored.
     * @param ?int $locationId null for a resource every location shares.
     * @param int $capacity bookings it can hold at the same time.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly Name $name,
        public readonly Slug $groupKey,
        public readonly ?int $locationId = null,
        public readonly int $capacity = 1,
        public readonly Status $status = Status::Active,
    ) {
        self::assertIds($id, $locationId);
        if ($capacity < 1 || $capacity > self::MAX_CAPACITY) {
            throw new InvalidValue('invalid_capacity', 'A resource holds 1 to 1000 bookings at a time.');
        }
    }
}
