<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain;

/**
 * The stored weekly schedules. An owner's schedule is saved as a whole, the
 * way the admin edits it, so rules have no update or delete of their own.
 */
interface ScheduleRuleRepository
{
    /**
     * One query for all the owners (availability reads every candidate).
     *
     * @param list<Owner> $owners
     * @return list<ScheduleRule> By owner, weekday and start.
     */
    public function ofOwners(array $owners): array;

    /**
     * Replaces the owner's weekly schedule; an empty list clears it.
     *
     * @param list<ScheduleRule> $rules All of this owner; their ids are ignored.
     */
    public function replace(Owner $owner, array $rules): void;
}
