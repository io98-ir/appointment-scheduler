<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Application;

use DateTimeImmutable;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Catalog\Contracts\Offer;
use Vaqtyar\Modules\Catalog\Contracts\ResourceUnit;
use Vaqtyar\Modules\Catalog\Contracts\StaffOffer;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Modules\Scheduling\Contracts\BookingWindow;
use Vaqtyar\Modules\Scheduling\Contracts\BookingWindows;
use Vaqtyar\Modules\Scheduling\Contracts\BusySpan;
use Vaqtyar\Modules\Scheduling\Contracts\Claim;
use Vaqtyar\Modules\Scheduling\Contracts\ClaimScope;
use Vaqtyar\Modules\Scheduling\Contracts\OccupancyReader;
use Vaqtyar\Modules\Scheduling\Contracts\SlotClaims;
use Vaqtyar\Modules\Scheduling\Domain\Availability\AvailabilityCalculator;
use Vaqtyar\Modules\Scheduling\Domain\Availability\LocalDay;
use Vaqtyar\Modules\Scheduling\Domain\Availability\Occupancy;
use Vaqtyar\Modules\Scheduling\Domain\Availability\ResourceCandidate;
use Vaqtyar\Modules\Scheduling\Domain\Availability\ResourceGroup;
use Vaqtyar\Modules\Scheduling\Domain\Availability\Slot;
use Vaqtyar\Modules\Scheduling\Domain\Availability\SlotRequest;
use Vaqtyar\Modules\Scheduling\Domain\Availability\SlotStaff;
use Vaqtyar\Modules\Scheduling\Domain\Availability\StaffCandidate;
use Vaqtyar\Modules\Scheduling\Domain\DayPlan;
use Vaqtyar\Modules\Scheduling\Domain\HolidayRepository;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\OwnerType;
use Vaqtyar\Modules\Scheduling\Domain\RuleKind;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleException;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleExceptionRepository;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRuleRepository;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\Slug;

/**
 * The public availability views (booking-engine §2): one day's starts, a
 * month of day statuses, and the first day with a free start.
 *
 * The inputs of the calculator are read in one batch per week of days: the
 * offer, the schedules of the candidates and the location, holidays and the
 * occupancies; the weekly rules once per request. A location or resource
 * without a weekly schedule is open whenever the location is (its
 * exceptions and holidays still apply). A computed day
 * is cached without the booking window, which moves with the clock and is
 * applied on every read. Nothing here decides a booking: the hold re-checks
 * the database under locks (ADR-004) through claim(), which skips the cache.
 */
final class AvailabilityService implements SlotClaims
{
    public const MAX_DAYS = 62;

    private const WEEK = 7;

    /** A day spans at most 25 hours (DST), so a grid started that day ends within two. */
    private const TWO_DAYS_MIN = 2 * 1440;

    private readonly AvailabilityCalculator $calculator;

    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly ScheduleRuleRepository $rules,
        private readonly ScheduleExceptionRepository $exceptions,
        private readonly HolidayRepository $holidays,
        private readonly OccupancyReader $occupancies,
        private readonly SlotCache $cache,
        private readonly Clock $clock,
        private readonly AvailabilityDefaults $defaults,
        private readonly ?BookingWindows $windows = null,
    ) {
        $this->calculator = new AvailabilityCalculator();
    }

    /**
     * @param ?LocalDate $date null is today at the location.
     */
    public function day(AvailabilityQuery $query, ?LocalDate $date): Availability
    {
        $request = $this->prepare($query);

        return new Availability(
            $request->location->timezone,
            $this->days($request, $date ?? $this->today($request), 1, false)
        );
    }

    /**
     * @param ?LocalDate $from null is today at the location.
     * @param int $days 1 to MAX_DAYS.
     */
    public function month(AvailabilityQuery $query, ?LocalDate $from, int $days): Availability
    {
        $request = $this->prepare($query);

        return new Availability(
            $request->location->timezone,
            $this->days($request, $from ?? $this->today($request), self::dayCount($days), false)
        );
    }

    /**
     * The first of $days days from $from with a free start; none when there
     * is none.
     *
     * @param ?LocalDate $from null is today at the location.
     */
    public function first(AvailabilityQuery $query, ?LocalDate $from, int $days): Availability
    {
        $request = $this->prepare($query);
        $found = \array_values(\array_filter(
            $this->days($request, $from ?? $this->today($request), self::dayCount($days), true),
            static fn (DayAvailability $day): bool => DayStatus::Available === $day->status
        ));

        return new Availability($request->location->timezone, $found);
    }

    public function scope(AvailabilityQuery $query, int $start): ClaimScope
    {
        $request = $this->prepare($query);
        $resourceIds = [];
        foreach ($request->groups as [, $units]) {
            foreach ($units as $unit) {
                $resourceIds[$unit->resourceId] = $unit->resourceId;
            }
        }

        return new ClaimScope(
            \array_map(static fn (StaffOffer $s): int => $s->staffId, $request->staff),
            \array_values($resourceIds),
            $start - $request->slot->bufferBeforeMin * 60,
            $start + ($request->longestMin + $request->slot->extrasMin + $request->slot->bufferAfterMin) * 60
        );
    }

    public function claim(AvailabilityQuery $query, int $start): ?Claim
    {
        $request = $this->prepare($query);
        $date = LocalDate::fromDateTime(new DateTimeImmutable('@' . $start), $request->location->timezone);
        $rules = null;
        [$day, $open, $staff, $groups] = $this->inputs($request, [$date], $rules)[$date->toString()];
        $pick = $open ? $this->calculator->pick(
            $request->slot->withWindow($request->minNoticeMin, $request->maxAdvanceMin),
            $day,
            $this->clock->now(),
            $staff,
            $groups,
            $start
        ) : null;
        if (null === $pick) {
            return null;
        }

        return new Claim(
            $pick->staff->staffId,
            $start,
            $pick->staff->end,
            $start - $request->slot->bufferBeforeMin * 60,
            $pick->staff->end + $request->slot->bufferAfterMin * 60,
            $pick->resourceIds
        );
    }

    /**
     * @return list<DayAvailability> Each day from $from, or up to the first available one.
     */
    private function days(PreparedQuery $request, LocalDate $from, int $count, bool $untilFirst): array
    {
        $now = $this->clock->now()->getTimestamp();
        $earliest = $now + $request->minNoticeMin * 60;
        $latest = $now + $request->maxAdvanceMin * 60;

        $rules = null;
        $result = [];
        for ($offset = 0; $offset < $count; $offset += self::WEEK) {
            $dates = [];
            for ($i = $offset; $i < \min($offset + self::WEEK, $count); ++$i) {
                $dates[] = $from->addDays($i);
            }
            $computed = $this->computed($request, $dates, $earliest, $latest, $rules);
            foreach ($dates as $date) {
                $key = $date->toString();
                $day = isset($computed[$key])
                    ? self::window($date, $computed[$key], $earliest, $latest)
                    : new DayAvailability($date, DayStatus::Closed, []);
                $result[] = $day;
                if ($untilFirst && DayStatus::Available === $day->status) {
                    return $result;
                }
            }
        }

        return $result;
    }

    /**
     * The cached or computed days among $dates that meet the booking
     * window; the others are closed without reading anything.
     *
     * @param list<LocalDate> $dates
     * @param ?array<string, list<ScheduleRule>> $rules The weekly rules by owner, read once per request.
     * @return array<string, array{bool, list<Slot>}> By date: whether anyone works it, and every start.
     */
    private function computed(PreparedQuery $request, array $dates, int $earliest, int $latest, ?array &$rules): array
    {
        $computed = [];
        $missing = [];
        foreach ($dates as $date) {
            $day = new LocalDay($date, $request->location->timezone);
            if ($day->end() <= $earliest || $day->start() > $latest) {
                continue;
            }
            $cached = $this->cache->get($request->cacheKey . $date->toString());
            $decoded = null === $cached ? null : self::decode($cached);
            if (null === $decoded) {
                $missing[] = $date;
            } else {
                $computed[$date->toString()] = $decoded;
            }
        }
        if ([] === $missing) {
            return $computed;
        }

        foreach ($this->compute($request, $missing, $rules) as $key => $day) {
            $this->cache->set($request->cacheKey . $key, self::encode($day));
            $computed[$key] = $day;
        }

        return $computed;
    }

    /**
     * @param non-empty-list<LocalDate> $dates In order.
     * @param ?array<string, list<ScheduleRule>> $rules Read on the first call.
     * @param-out array<string, list<ScheduleRule>> $rules
     * @return array<string, array{bool, list<Slot>}>
     */
    private function compute(PreparedQuery $request, array $dates, ?array &$rules): array
    {
        $days = [];
        foreach ($this->inputs($request, $dates, $rules) as $key => [$day, $open, $staff, $groups]) {
            $days[$key] = [
                $open,
                $open ? $this->calculator->slots(
                    $request->slot,
                    $day,
                    (new DateTimeImmutable('@' . $day->start())),
                    $staff,
                    $groups
                ) : [],
            ];
        }

        return $days;
    }

    /**
     * What the calculator takes for each of $dates, read from the
     * repositories and the occupancies, never from the cache: the day,
     * whether anyone works it, the staff and the resource groups.
     *
     * @param non-empty-list<LocalDate> $dates In order.
     * @param ?array<string, list<ScheduleRule>> $rules Read on the first call.
     * @param-out array<string, list<ScheduleRule>> $rules
     * @return array<string, array{LocalDay, bool, list<StaffCandidate>, list<ResourceGroup>}>
     */
    private function inputs(PreparedQuery $request, array $dates, ?array &$rules): array
    {
        $zone = $request->location->timezone;
        $first = $dates[0];
        $last = $dates[\count($dates) - 1];
        $staffIds = \array_map(static fn (StaffOffer $s): int => $s->staffId, $request->staff);
        $resourceIds = [];
        foreach ($request->groups as [, $units]) {
            foreach ($units as $unit) {
                $resourceIds[$unit->resourceId] = $unit->resourceId;
            }
        }
        $resourceIds = \array_values($resourceIds);

        $owners = [new Owner(OwnerType::Location, $request->location->locationId)];
        foreach ($staffIds as $id) {
            $owners[] = new Owner(OwnerType::Staff, $id);
        }
        foreach ($resourceIds as $id) {
            $owners[] = new Owner(OwnerType::Resource, $id);
        }
        $rules ??= $this->weeklyRules($owners);
        $exceptions = self::byOwner($this->exceptions->between($owners, $first, $last));
        $holidays = [];
        $calendar = $request->location->holidayCalendar;
        if (null !== $calendar) {
            foreach ($this->holidays->between(Slug::fromInput($calendar), $first, $last) as $holiday) {
                $holidays[$holiday->date->toString()] = true;
            }
        }

        $before = $request->slot->bufferBeforeMin * 60;
        $after = ($request->longestMin + $request->slot->extrasMin + $request->slot->bufferAfterMin) * 60;
        $busy = $this->occupancies->overlapping(
            $staffIds,
            $resourceIds,
            (new LocalDay($first, $zone))->start() - $before,
            (new LocalDay($last, $zone))->end() + $after
        );

        $days = [];
        foreach ($dates as $date) {
            $day = new LocalDay($date, $zone);
            $holiday = isset($holidays[$date->toString()]);
            $plan = static fn (OwnerType $type, int $id): DayPlan => DayPlan::of(
                $date,
                $rules[self::key($type, $id)] ?? [],
                $exceptions[self::key($type, $id)] ?? [],
                $holiday
            );
            $location = $plan(OwnerType::Location, $request->location->locationId);
            $from = $day->start() - $before;
            $to = $day->end() + $after;
            $spans = \array_values(\array_filter(
                $busy,
                static fn (BusySpan $span): bool => $span->start < $to && $span->end > $from
            ));

            $open = false;
            $staff = [];
            foreach ($request->staff as $member) {
                $own = $plan(OwnerType::Staff, $member->staffId);
                $working = $day->utc($own->working->intersect($location->working));
                $blocked = $day->utc($own->blocked->union($location->blocked));
                $open = $open || !$working->subtract($blocked)->isEmpty();
                $staff[] = new StaffCandidate(
                    $member->staffId,
                    $member->durationMin,
                    $working,
                    $blocked,
                    self::occupancies($spans, false, $member->staffId),
                    $member->priority
                );
            }
            $groups = [];
            foreach ($request->groups as [$quantity, $units]) {
                $candidates = [];
                foreach ($units as $unit) {
                    $own = $plan(OwnerType::Resource, $unit->resourceId);
                    $candidates[] = new ResourceCandidate(
                        $unit->resourceId,
                        $unit->capacity,
                        $day->utc($own->working->intersect($location->working)),
                        $day->utc($own->blocked->union($location->blocked)),
                        self::occupancies($spans, true, $unit->resourceId)
                    );
                }
                $groups[] = new ResourceGroup($quantity, $candidates);
            }

            $days[$date->toString()] = [$day, $open, $staff, $groups];
        }

        return $days;
    }

    /**
     * Checks the query against the catalog and resolves what the
     * calculator needs, once for all the days.
     */
    private function prepare(AvailabilityQuery $query): PreparedQuery
    {
        $offer = $this->catalog->offer($query->variantId)
            ?? throw new NotFound('variant_not_found', 'The service cannot be booked.');
        $location = $this->catalog->location($query->locationId)
            ?? throw new NotFound('location_not_found', 'The location cannot be booked.');
        if ($query->partySize > $offer->capacity) {
            throw new InvalidValue('party_too_large', 'The party is larger than the service takes at once.');
        }

        $here = static fn (?int $locationId): bool => null === $locationId || $location->locationId === $locationId;
        $staff = \array_values(\array_filter(
            $offer->staff,
            static fn (StaffOffer $s): bool => $here($s->locationId)
                && (null === $query->staffId || $query->staffId === $s->staffId)
        ));
        if (null !== $query->staffId && [] === $staff) {
            throw new InvalidValue('unknown_staff', 'The staff member does not serve this here.');
        }
        $groups = [];
        foreach ($offer->resources as $need) {
            $groups[] = [
                $need->quantity,
                \array_values(\array_filter(
                    $need->units,
                    static fn (ResourceUnit $unit): bool => $here($unit->locationId)
                )),
            ];
        }

        $extrasMin = self::extrasMin($offer, $query->extraIds);
        $stepMin = $offer->slotStepMin ?? $this->defaults->stepMin;
        $slot = new SlotRequest(
            $offer->variantId,
            $offer->capacity,
            $query->partySize,
            $extrasMin,
            $offer->bufferBeforeMin,
            $offer->bufferAfterMin,
            $stepMin,
            0,
            self::TWO_DAYS_MIN,
            $this->defaults->choice
        );
        $longest = \array_reduce($staff, static fn (int $max, StaffOffer $s): int => \max($max, $s->durationMin), 0);
        $cacheKey = \implode(':', [
            $offer->variantId,
            $location->locationId,
            $query->staffId ?? 0,
            $extrasMin,
            $query->partySize,
            $stepMin,
            $this->defaults->choice->value,
            '',
        ]);

        // The service's own window over the site's; applied after the cache, so it is not in the key.
        $window = $this->windows?->forService($offer->serviceId) ?? BookingWindow::site();

        return new PreparedQuery(
            $location,
            $staff,
            $groups,
            $slot,
            $longest,
            $cacheKey,
            $window->minNoticeMin ?? $this->defaults->minNoticeMin,
            $window->maxAdvanceMin ?? $this->defaults->maxAdvanceMin
        );
    }

    /**
     * @param list<int> $extraIds An id once per unit.
     */
    private static function extrasMin(Offer $offer, array $extraIds): int
    {
        $offered = [];
        foreach ($offer->extras as $extra) {
            $offered[$extra->extraId] = $extra;
        }
        $minutes = 0;
        foreach (\array_count_values($extraIds) as $id => $units) {
            $extra = $offered[$id] ?? null;
            if (null === $extra || $units > $extra->maxQty) {
                throw new InvalidValue('invalid_extra', 'An extra is not offered with this service, or not that many.');
            }
            $minutes += $extra->durationMin * $units;
        }

        return $minutes;
    }

    /**
     * @param array{bool, list<Slot>} $computed
     */
    private static function window(LocalDate $date, array $computed, int $earliest, int $latest): DayAvailability
    {
        [$open, $slots] = $computed;
        if (!$open) {
            return new DayAvailability($date, DayStatus::Closed, []);
        }
        $slots = \array_values(\array_filter(
            $slots,
            static fn (Slot $slot): bool => $slot->start >= $earliest && $slot->start <= $latest
        ));

        return new DayAvailability($date, [] === $slots ? DayStatus::Full : DayStatus::Available, $slots);
    }

    private function today(PreparedQuery $request): LocalDate
    {
        return LocalDate::fromDateTime($this->clock->now(), $request->location->timezone);
    }

    private static function dayCount(int $days): int
    {
        if ($days < 1 || $days > self::MAX_DAYS) {
            throw new InvalidValue('invalid_days', 'Ask for 1 to 62 days.');
        }

        return $days;
    }

    /**
     * @param list<BusySpan> $spans
     * @return list<Occupancy>
     */
    private static function occupancies(array $spans, bool $onResource, int $ownerId): array
    {
        $mine = [];
        foreach ($spans as $span) {
            if ($span->onResource === $onResource && $span->ownerId === $ownerId) {
                $mine[] = new Occupancy($span->start, $span->end, $span->seats, $span->variantId, $span->staffId);
            }
        }

        return $mine;
    }

    /**
     * The weekly rules by owner. A location or a resource without any is
     * open all day, every day, so only the location's hours and the
     * exceptions bound it: a room is rarely given hours of its own. A staff
     * member without any does not work.
     *
     * @param list<Owner> $owners
     * @return array<string, list<ScheduleRule>>
     */
    private function weeklyRules(array $owners): array
    {
        $rules = self::byOwner($this->rules->ofOwners($owners));
        foreach ($owners as $owner) {
            if (OwnerType::Staff !== $owner->type) {
                $rules[self::key($owner->type, $owner->id)] ??= self::alwaysOpen($owner);
            }
        }

        return $rules;
    }

    /**
     * Work all day, every day: the hours of an owner without a schedule.
     *
     * @return list<ScheduleRule>
     */
    private static function alwaysOpen(Owner $owner): array
    {
        $rules = [];
        for ($weekday = 0; $weekday < 7; ++$weekday) {
            $rules[] = new ScheduleRule(
                null,
                $owner,
                $weekday,
                LocalTime::fromMinutes(0),
                LocalTime::fromMinutes(1440),
                RuleKind::Work
            );
        }

        return $rules;
    }

    /**
     * @template T of ScheduleRule|ScheduleException
     * @param list<T> $items
     * @return array<string, list<T>>
     */
    private static function byOwner(array $items): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $grouped[self::key($item->owner->type, $item->owner->id)][] = $item;
        }

        return $grouped;
    }

    private static function key(OwnerType $type, int $id): string
    {
        return $type->value . ':' . $id;
    }

    /**
     * @param array{bool, list<Slot>} $day
     * @return array{bool, list<array{int, list<array{int, int, int}>}>}
     */
    private static function encode(array $day): array
    {
        return [$day[0], \array_map(
            static fn (Slot $slot): array => [
                $slot->start,
                \array_map(static fn (SlotStaff $s): array => [$s->staffId, $s->end, $s->seatsLeft], $slot->staff),
            ],
            $day[1]
        )];
    }

    /**
     * Null for anything but what encode() gives, such as an entry of an
     * older version of the plugin.
     *
     * @param array<mixed> $cached
     * @return ?array{bool, list<Slot>}
     */
    private static function decode(array $cached): ?array
    {
        if (!\is_bool($cached[0] ?? null) || !\is_array($cached[1] ?? null)) {
            return null;
        }
        $slots = [];
        foreach ($cached[1] as $slot) {
            if (!\is_array($slot) || !\is_int($slot[0] ?? null) || !\is_array($slot[1] ?? null)) {
                return null;
            }
            $staff = [];
            foreach ($slot[1] as $s) {
                if (!\is_array($s) || !\is_int($s[0] ?? null) || !\is_int($s[1] ?? null) || !\is_int($s[2] ?? null)) {
                    return null;
                }
                $staff[] = new SlotStaff($s[0], $s[1], $s[2]);
            }
            if ([] === $staff) {
                return null;
            }
            $slots[] = new Slot($slot[0], $staff);
        }

        return [$cached[0], $slots];
    }
}
