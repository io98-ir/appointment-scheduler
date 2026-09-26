<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain\Availability;

use DateTimeImmutable;
use Vaqtyar\Shared\Domain\IntervalSet;

/**
 * The free start times of one variant on one local day (booking-engine §2).
 *
 * Candidate starts are a grid of stepMin minutes from local midnight, so
 * every staff member offers the same times. A staff member takes a start
 * when the whole booking, buffers included,
 * [from, to) = [start − before, start + own duration + extras + after), is
 *  - inside their working time and outside their blocked time;
 *  - clear of their other bookings, except the session it would join: the
 *    same variant over exactly [from, to), whose seats plus the party fit
 *    the service capacity. Staggered, overlapping sessions are not allowed;
 *  - and each resource group has enough units that either hold that
 *    session already, or are working, not blocked and host fewer sessions
 *    than their capacity over [from, to). A session counts once on a
 *    resource, whatever its seats.
 * Starts before now + minimum notice or after now + maximum advance are left
 * out. The staff of a slot are ordered by StaffChoice.
 *
 * The result only offers times: the hold re-checks against the database
 * under locks (ADR-004), never against this.
 */
final class AvailabilityCalculator
{
    /**
     * @param list<StaffCandidate> $staff
     * @param list<ResourceGroup> $resources
     * @return list<Slot> By start.
     */
    public function slots(
        SlotRequest $request,
        LocalDay $day,
        DateTimeImmutable $now,
        array $staff,
        array $resources,
    ): array {
        $prepared = self::prepare($request, $staff, $resources);
        if (null === $prepared) {
            return [];
        }
        [$candidates, $groups] = $prepared;

        [$earliest, $latest] = self::window($request, $now);
        $step = $request->stepMin * 60;
        $slots = [];
        for ($start = $day->start(); $start < $day->end() && $start <= $latest; $start += $step) {
            if ($start < $earliest) {
                continue;
            }
            $fits = [];
            foreach ($candidates as $candidate) {
                $fit = self::fit($request, $candidate, $groups, $start);
                if (null !== $fit) {
                    $fits[] = $fit[0];
                }
            }
            if ([] !== $fits) {
                $slots[] = new Slot($start, $fits);
            }
        }

        return $slots;
    }

    /**
     * What a hold takes at $start (booking-engine §3): the first staff
     * member in StaffChoice order who fits, and the units of each resource
     * group. Null when $start is not on the day's grid or in the booking
     * window, or nobody fits: the rules of slots(), for one start.
     *
     * @param list<StaffCandidate> $staff
     * @param list<ResourceGroup> $resources
     */
    public function pick(
        SlotRequest $request,
        LocalDay $day,
        DateTimeImmutable $now,
        array $staff,
        array $resources,
        int $start,
    ): ?Pick {
        [$earliest, $latest] = self::window($request, $now);
        $onGrid = $start >= $day->start() && $start < $day->end()
            && 0 === ($start - $day->start()) % ($request->stepMin * 60);
        $prepared = self::prepare($request, $staff, $resources);
        if (!$onGrid || $start < $earliest || $start > $latest || null === $prepared) {
            return null;
        }
        [$candidates, $groups] = $prepared;
        foreach ($candidates as $candidate) {
            $fit = self::fit($request, $candidate, $groups, $start);
            if (null !== $fit) {
                return new Pick($start, $fit[0], $fit[1]);
            }
        }

        return null;
    }

    /**
     * The staff in StaffChoice order and the resource groups, as fit()
     * takes them; null when nothing can fit.
     *
     * @param list<StaffCandidate> $staff
     * @param list<ResourceGroup> $resources
     * @return ?array{
     *     list<array{int, int, IntervalSet, list<Occupancy>}>,
     *     list<array{int, list<array{int, IntervalSet, list<Occupancy>, int}>}>
     * }
     */
    private static function prepare(SlotRequest $request, array $staff, array $resources): ?array
    {
        if ($request->partySize > $request->capacity || [] === $staff) {
            return null;
        }
        foreach ($resources as $group) {
            if (\count($group->units) < $group->quantity) {
                return null;
            }
        }

        return [
            self::prepareStaff($request, $staff),
            \array_map(
                static fn (ResourceGroup $group): array => [
                    $group->quantity,
                    \array_map(
                        static fn (ResourceCandidate $unit): array => [
                            $unit->resourceId,
                            $unit->working->subtract($unit->blocked),
                            $unit->occupancies,
                            $unit->capacity,
                        ],
                        $group->units
                    ),
                ],
                $resources
            ),
        ];
    }

    /**
     * @return array{int, int} The earliest and the latest start, UTC seconds.
     */
    private static function window(SlotRequest $request, DateTimeImmutable $now): array
    {
        return [
            $now->getTimestamp() + $request->minNoticeMin * 60,
            $now->getTimestamp() + $request->maxAdvanceMin * 60,
        ];
    }

    /**
     * Whether one staff member takes $start, and with which resource units.
     *
     * @param array{int, int, IntervalSet, list<Occupancy>} $candidate
     * @param list<array{int, list<array{int, IntervalSet, list<Occupancy>, int}>}> $groups
     * @return ?array{SlotStaff, list<int>}
     */
    private static function fit(SlotRequest $request, array $candidate, array $groups, int $start): ?array
    {
        [$id, $duration, $free, $sameVariant] = $candidate;
        $end = $start + $duration + $request->extrasMin * 60;
        $from = $start - $request->bufferBeforeMin * 60;
        $to = $end + $request->bufferAfterMin * 60;
        if (!$free->covers($from, $to)) {
            return null;
        }
        $used = self::sessionSeats($sameVariant, $request->variantId, $id, $from, $to);
        if (null === $used || $used + $request->partySize > $request->capacity) {
            return null;
        }
        $units = self::freeUnits($groups, $request->variantId, $id, $from, $to);

        return null === $units ? null : [new SlotStaff($id, $end, $request->capacity - $used), $units];
    }

    /**
     * Each staff member as [id, duration in seconds, free time, bookings of
     * this variant], in StaffChoice order. Free time is working − blocked −
     * the bookings of other variants, which no session can share.
     *
     * @param list<StaffCandidate> $staff
     * @return list<array{int, int, IntervalSet, list<Occupancy>}>
     */
    private static function prepareStaff(SlotRequest $request, array $staff): array
    {
        $prepared = [];
        foreach ($staff as $member) {
            $other = [];
            $same = [];
            $busy = 0;
            foreach ($member->occupancies as $occupancy) {
                $busy += $occupancy->end - $occupancy->start;
                if ($occupancy->variantId === $request->variantId) {
                    $same[] = $occupancy;
                } else {
                    $other[] = [$occupancy->start, $occupancy->end];
                }
            }
            $order = StaffChoice::LeastBusy === $request->choice
                ? [$busy, $member->priority, $member->staffId]
                : [$member->priority, $member->staffId];
            $prepared[] = [
                $order,
                [
                    $member->staffId,
                    $member->durationMin * 60,
                    $member->working->subtract($member->blocked)->subtract(IntervalSet::of($other)),
                    $same,
                ],
            ];
        }
        \usort($prepared, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return \array_column($prepared, 1);
    }

    /**
     * The seats already taken in the session this booking would join, or
     * null when another booking of the variant overlaps [from, to) without
     * being that session.
     *
     * @param list<Occupancy> $occupancies Of the requested variant.
     */
    private static function sessionSeats(array $occupancies, int $variantId, int $staffId, int $from, int $to): ?int
    {
        $seats = 0;
        foreach ($occupancies as $occupancy) {
            if ($occupancy->start >= $to || $occupancy->end <= $from) {
                continue;
            }
            if (!$occupancy->isSession($variantId, $staffId, $from, $to)) {
                return null;
            }
            $seats += $occupancy->seats;
        }

        return $seats;
    }

    /**
     * The units taken, the first that fit in each group, or null when a
     * group has too few.
     *
     * @param list<array{int, list<array{int, IntervalSet, list<Occupancy>, int}>}> $groups
     * @return ?list<int>
     */
    private static function freeUnits(array $groups, int $variantId, int $staffId, int $from, int $to): ?array
    {
        $taken = [];
        foreach ($groups as [$quantity, $units]) {
            $free = 0;
            foreach ($units as [$unitId, $time, $occupancies, $capacity]) {
                if ($free >= $quantity) {
                    break;
                }
                if (self::unitFits($time, $occupancies, $capacity, $variantId, $staffId, $from, $to)) {
                    ++$free;
                    $taken[] = $unitId;
                }
            }
            if ($free < $quantity) {
                return null;
            }
        }

        return $taken;
    }

    /**
     * A unit fits when it already holds the session being joined, or when it
     * is free and hosts fewer sessions than its capacity at every moment of
     * [from, to). Occupancies of one session count once; one without a
     * variant or staff member is a session of its own.
     *
     * @param list<Occupancy> $occupancies
     */
    private static function unitFits(
        IntervalSet $time,
        array $occupancies,
        int $capacity,
        int $variantId,
        int $staffId,
        int $from,
        int $to,
    ): bool {
        $sessions = [];
        foreach ($occupancies as $i => $occupancy) {
            if ($occupancy->start >= $to || $occupancy->end <= $from) {
                continue;
            }
            if ($occupancy->isSession($variantId, $staffId, $from, $to)) {
                return true;
            }
            $key = null === $occupancy->variantId || null === $occupancy->staffId
                ? 'row' . $i
                : $occupancy->variantId . ':' . $occupancy->staffId . ':' . $occupancy->start . ':' . $occupancy->end;
            $sessions[$key] = [\max($occupancy->start, $from), \min($occupancy->end, $to)];
        }
        if (!$time->covers($from, $to)) {
            return false;
        }

        return self::mostAtOnce(\array_values($sessions)) + 1 <= $capacity;
    }

    /**
     * The most intervals that overlap at one moment: a sweep in which an end
     * and a start at the same second do not overlap (half-open intervals).
     *
     * @param list<array{int, int}> $intervals
     */
    private static function mostAtOnce(array $intervals): int
    {
        $events = [];
        foreach ($intervals as [$start, $end]) {
            $events[] = [$start, 1];
            $events[] = [$end, -1];
        }
        // Ends (-1) sort before starts (+1) at the same second.
        \sort($events);
        $count = 0;
        $most = 0;
        foreach ($events as [, $change]) {
            $count += $change;
            $most = \max($most, $count);
        }

        return $most;
    }
}
