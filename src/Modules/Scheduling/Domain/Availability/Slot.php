<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain\Availability;

/**
 * A start time that at least one staff member can take.
 */
final class Slot
{
    /**
     * @param int $start UTC seconds.
     * @param non-empty-list<SlotStaff> $staff in the order of StaffChoice; the first is assigned.
     */
    public function __construct(public readonly int $start, public readonly array $staff)
    {
    }

    /**
     * @return list<int>
     */
    public function staffIds(): array
    {
        return \array_map(static fn (SlotStaff $s): int => $s->staffId, $this->staff);
    }
}
