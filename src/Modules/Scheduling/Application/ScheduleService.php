<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Application;

use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleException;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleExceptionRepository;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRuleRepository;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\NotFound;

/**
 * The admin's schedule use cases: the weekly schedule and the dated
 * exceptions (time off, extra hours, blocked time) of a staff member,
 * resource or location. Each checks the capability again, after the REST
 * permission callback (architecture §12), and that the owner is in the
 * catalog; an inactive owner's schedule can still be edited.
 */
final class ScheduleService
{
    public const CAPABILITY = 'manage_schedules';

    /** Days of exceptions one read may cover: a year, with the leap day. */
    public const MAX_RANGE_DAYS = 366;

    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly CatalogApi $catalog,
        private readonly ScheduleRuleRepository $rules,
        private readonly ScheduleExceptionRepository $exceptions,
    ) {
    }

    /**
     * @return list<ScheduleRule> By weekday and start.
     */
    public function weekly(Owner $owner): array
    {
        $this->authorize($owner);

        return $this->rules->ofOwners([$owner]);
    }

    /**
     * Two work ranges (or two breaks) of one weekday may touch but not
     * overlap: the admin meant one of them, and the grid would count both.
     *
     * @param list<ScheduleRule> $rules The whole week; an empty list clears it.
     * @return list<ScheduleRule> As stored.
     */
    public function replaceWeekly(Owner $owner, array $rules): array
    {
        $this->authorize($owner);
        $sorted = $rules;
        \usort(
            $sorted,
            static fn (ScheduleRule $a, ScheduleRule $b): int
                => [$a->kind->value, $a->weekday, $a->start->minutes]
                <=> [$b->kind->value, $b->weekday, $b->start->minutes]
        );
        $previous = null;
        foreach ($sorted as $rule) {
            if (
                null !== $previous
                && $previous->kind === $rule->kind
                && $previous->weekday === $rule->weekday
                && $rule->start->isBefore($previous->end)
            ) {
                throw new InvalidValue('overlapping_rules', 'Two ranges of one kind overlap on one weekday.');
            }
            $previous = $rule;
        }
        $this->rules->replace($owner, $rules);

        return $this->rules->ofOwners([$owner]);
    }

    /**
     * @return list<ScheduleException> Dates $from to $to, both included, by date and start.
     */
    public function exceptions(Owner $owner, LocalDate $from, LocalDate $to): array
    {
        $this->authorize($owner);
        $days = $from->daysUntil($to);
        if ($days < 0 || $days >= self::MAX_RANGE_DAYS) {
            throw new InvalidValue('invalid_range', 'The range ends before it starts or is longer than a year.');
        }

        return $this->exceptions->between([$owner], $from, $to);
    }

    /**
     * Inserts when the id is null, else replaces the stored exception, which
     * may move to another owner or date.
     */
    public function saveException(ScheduleException $exception): ScheduleException
    {
        $this->authorize($exception->owner);
        if (null !== $exception->id) {
            $this->exception($exception->id);
        }

        return $this->exceptions->save($exception);
    }

    public function deleteException(int $id): void
    {
        $this->authorizeUser();
        $this->exception($id);
        $this->exceptions->delete($id);
    }

    private function exception(int $id): ScheduleException
    {
        return $this->exceptions->find($id) ?? throw new NotFound('exception_not_found', 'No exception has this id.');
    }

    private function authorize(Owner $owner): void
    {
        $this->authorizeUser();
        if (!$this->catalog->isStored($owner->type->value, $owner->id)) {
            $type = $owner->type->value;

            throw new NotFound("{$type}_not_found", "No {$type} has this id.");
        }
    }

    private function authorizeUser(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }
}
