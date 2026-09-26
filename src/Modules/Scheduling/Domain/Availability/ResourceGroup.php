<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain\Availability;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * A variant needs $quantity resources of a group, any of its units
 * (booking-engine §1).
 */
final class ResourceGroup
{
    /**
     * @param list<ResourceCandidate> $units
     */
    public function __construct(public readonly int $quantity, public readonly array $units)
    {
        if ($quantity < 1) {
            throw new InvalidValue('invalid_quantity', 'A group is needed at least once.');
        }
    }
}
