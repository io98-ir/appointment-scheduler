<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Scheduling\Domain\Availability;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Scheduling\Domain\Availability\AvailabilityCalculator;
use Vaqtyar\Modules\Scheduling\Domain\Availability\Occupancy;
use Vaqtyar\Modules\Scheduling\Domain\Availability\ResourceCandidate;
use Vaqtyar\Modules\Scheduling\Domain\Availability\ResourceGroup;
use Vaqtyar\Modules\Scheduling\Domain\Availability\Slot;
use Vaqtyar\Modules\Scheduling\Domain\Availability\SlotRequest;
use Vaqtyar\Modules\Scheduling\Domain\Availability\StaffCandidate;
use Vaqtyar\Modules\Scheduling\Domain\Availability\StaffChoice;
use Vaqtyar\Shared\Domain\IntervalSet;

/**
 * The calculator against a minute-by-minute brute force on random days
 * (fixed seeds, so a failure repeats). The brute force is slow and obvious
 * on purpose. Each day also has group sessions that the request could join,
 * on the staff member and on some resources.
 */
final class AvailabilityRandomizedTest extends TestCase
{
    use Scenario;

    private const VARIANT = 5;
    private const RUNS = 300;

    public function testMatchesTheMinuteByMinuteBruteForce(): void
    {
        for ($seed = 1; $seed <= self::RUNS; ++$seed) {
            \mt_srand($seed);
            $request = self::randomRequest();
            $now = self::day()->start() + \mt_rand(-120, 600) * 60;

            // The units' occupancies apart, so the sessions below can be added to them.
            $quantities = [];
            $units = [];
            $unitBusy = [];
            for ($g = 0, $n = \mt_rand(0, 2); $g < $n; ++$g) {
                $quantities[$g] = \mt_rand(1, 2);
                for ($u = 0, $m = \mt_rand(1, 3); $u < $m; ++$u) {
                    $units[$g][$u] = [$g * 10 + $u + 1, \mt_rand(1, 3), self::randomSet(2), self::randomSet(1)];
                    $unitBusy[$g][$u] = self::randomOccupancies();
                }
            }
            $staff = [];
            for ($id = 1, $n = \mt_rand(1, 4); $id <= $n; ++$id) {
                $duration = [30, 45, 60, 90][\mt_rand(0, 3)];
                $occupancies = self::randomOccupancies();
                // Sessions this request could join: the exact span it would book.
                for ($s = 0, $k = \mt_rand(0, 2); $s < $k; ++$s) {
                    $grid = $request->stepMin * 60;
                    $start = self::day()->start() + \intdiv(\mt_rand(8 * 3600, 17 * 3600), $grid) * $grid;
                    $session = new Occupancy(
                        $start - $request->bufferBeforeMin * 60,
                        $start + ($duration + $request->extrasMin + $request->bufferAfterMin) * 60,
                        \mt_rand(1, $request->capacity),
                        self::VARIANT,
                        $id
                    );
                    $occupancies[] = $session;
                    foreach ($unitBusy as $g => $busy) {
                        if (0 === \mt_rand(0, 1)) {
                            $unitBusy[$g][\mt_rand(0, \count($busy) - 1)][] = $session;
                        }
                    }
                }
                $staff[] = new StaffCandidate(
                    $id,
                    $duration,
                    self::randomSet(3),
                    self::randomSet(1),
                    $occupancies,
                    \mt_rand(0, 3)
                );
            }
            $resources = [];
            foreach ($quantities as $g => $quantity) {
                $candidates = [];
                foreach ($units[$g] as $u => [$resourceId, $capacity, $working, $blocked]) {
                    $busy = $unitBusy[$g][$u];
                    $candidates[] = new ResourceCandidate($resourceId, $capacity, $working, $blocked, $busy);
                }
                $resources[] = new ResourceGroup($quantity, $candidates);
            }

            $expected = self::bruteForce($request, $now, $staff, $resources);
            $calculator = new AvailabilityCalculator();
            $slots = $calculator->slots($request, self::day(), new \DateTimeImmutable('@' . $now), $staff, $resources);
            $actual = self::flatten($slots);

            self::assertSame($expected, $actual, "Seed {$seed}");
            self::assertPicksMatch($calculator, $request, $now, $staff, $resources, $slots, "Seed {$seed}");
        }
    }

    /**
     * pick() at every minute of the day agrees with slots(): a start is
     * picked exactly when it is offered, by the slot's first staff member,
     * with one distinct unit per needed quantity from the right groups.
     *
     * @param list<StaffCandidate> $staff
     * @param list<ResourceGroup> $resources
     * @param list<\Vaqtyar\Modules\Scheduling\Domain\Availability\Slot> $slots
     */
    private static function assertPicksMatch(
        AvailabilityCalculator $calculator,
        SlotRequest $request,
        int $now,
        array $staff,
        array $resources,
        array $slots,
        string $message,
    ): void {
        $offered = [];
        foreach ($slots as $slot) {
            $offered[$slot->start] = $slot->staff[0];
        }
        $needed = 0;
        $groupOf = [];
        foreach ($resources as $g => $group) {
            $needed += $group->quantity;
            foreach ($group->units as $unit) {
                $groupOf[$unit->resourceId] = $g;
            }
        }
        for ($start = self::day()->start(); $start < self::day()->end(); $start += 300) {
            $at = new \DateTimeImmutable('@' . $now);
            $pick = $calculator->pick($request, self::day(), $at, $staff, $resources, $start);
            $slotStaff = $offered[$start] ?? null;
            self::assertSame(null === $slotStaff, null === $pick, "{$message}, start {$start}");
            if (null === $pick || null === $slotStaff) {
                continue;
            }
            self::assertEquals($slotStaff, $pick->staff, $message);
            self::assertCount($needed, \array_unique($pick->resourceIds), $message);
            $perGroup = [];
            foreach ($pick->resourceIds as $id) {
                $perGroup[$groupOf[$id]] = ($perGroup[$groupOf[$id]] ?? 0) + 1;
            }
            foreach ($resources as $g => $group) {
                self::assertSame($group->quantity, $perGroup[$g] ?? 0, $message);
            }
        }
    }

    private static function randomRequest(): SlotRequest
    {
        $capacity = \mt_rand(1, 3);

        return new SlotRequest(
            self::VARIANT,
            $capacity,
            \mt_rand(1, $capacity + 1),
            \mt_rand(0, 2) * 15,
            \mt_rand(0, 2) * 10,
            \mt_rand(0, 2) * 10,
            [10, 15, 20, 30, 45, 60][\mt_rand(0, 5)],
            \mt_rand(0, 2) * 60,
            \mt_rand(8, 24) * 60,
            0 === \mt_rand(0, 1) ? StaffChoice::LeastBusy : StaffChoice::Priority
        );
    }

    private static function randomSet(int $count): IntervalSet
    {
        $ranges = [];
        for ($i = 0; $i < $count; ++$i) {
            $start = \mt_rand(6 * 12, 20 * 12) * 5;
            $ranges[] = [self::minute($start), self::minute(\min(1440, $start + \mt_rand(1, 60) * 5))];
        }

        return IntervalSet::of($ranges);
    }

    /**
     * @return list<Occupancy>
     */
    private static function randomOccupancies(): array
    {
        $busy = [];
        for ($i = 0, $n = \mt_rand(0, 4); $i < $n; ++$i) {
            $start = \mt_rand(6 * 12, 21 * 12) * 5;
            $busy[] = new Occupancy(
                self::minute($start),
                self::minute($start + \mt_rand(1, 24) * 5),
                \mt_rand(1, 2),
                [null, self::VARIANT, 9][\mt_rand(0, 2)],
                [null, 1, 2][\mt_rand(0, 2)]
            );
        }

        return $busy;
    }

    private static function minute(int $minute): int
    {
        return self::day()->start() + $minute * 60;
    }

    /**
     * @param list<StaffCandidate> $staff
     * @param list<ResourceGroup> $groups
     * @return list<array{int, list<array{int, int, int}>}>
     */
    private static function bruteForce(SlotRequest $request, int $now, array $staff, array $groups): array
    {
        $slots = [];
        for ($start = self::day()->start(); $start < self::day()->end(); $start += $request->stepMin * 60) {
            if (
                $request->partySize > $request->capacity
                || $start < $now + $request->minNoticeMin * 60
                || $start > $now + $request->maxAdvanceMin * 60
            ) {
                continue;
            }
            $fits = [];
            foreach ($staff as $member) {
                $end = $start + ($member->durationMin + $request->extrasMin) * 60;
                $from = $start - $request->bufferBeforeMin * 60;
                $to = $end + $request->bufferAfterMin * 60;
                $joined = static fn (Occupancy $o): bool => self::VARIANT === $o->variantId
                    && (null === $o->staffId || $member->staffId === $o->staffId)
                    && $o->start === $from && $o->end === $to;
                $used = 0;
                foreach ($member->occupancies as $o) {
                    if ($joined($o)) {
                        $used += $o->seats;
                    }
                }
                $ok = $used + $request->partySize <= $request->capacity;
                for ($t = $from; $t < $to && $ok; $t += 60) {
                    foreach ($member->occupancies as $o) {
                        if ($o->start <= $t && $t < $o->end && !$joined($o)) {
                            $ok = false;
                        }
                    }
                    $ok = $ok && self::in($member->working, $t) && !self::in($member->blocked, $t);
                }
                if (!$ok) {
                    continue;
                }
                foreach ($groups as $group) {
                    $free = 0;
                    foreach ($group->units as $unit) {
                        $hosts = false;
                        foreach ($unit->occupancies as $o) {
                            $hosts = $hosts || $joined($o);
                        }
                        $unitOk = true;
                        for ($t = $from; $t < $to && $unitOk && !$hosts; $t += 60) {
                            $sessions = [];
                            foreach ($unit->occupancies as $i => $o) {
                                if ($o->start <= $t && $t < $o->end) {
                                    $sessions[null === $o->variantId || null === $o->staffId
                                        ? "row{$i}"
                                        : "{$o->variantId}:{$o->staffId}:{$o->start}:{$o->end}"] = true;
                                }
                            }
                            $unitOk = self::in($unit->working, $t)
                                && !self::in($unit->blocked, $t)
                                && \count($sessions) + 1 <= $unit->capacity;
                        }
                        $free += $hosts || $unitOk ? 1 : 0;
                    }
                    if ($free < $group->quantity) {
                        continue 2;
                    }
                }
                $busy = 0;
                foreach ($member->occupancies as $o) {
                    $busy += $o->end - $o->start;
                }
                $key = StaffChoice::LeastBusy === $request->choice
                    ? [$busy, $member->priority, $member->staffId]
                    : [$member->priority, $member->staffId];
                $fits[] = [$key, [$member->staffId, $end, $request->capacity - $used]];
            }
            if ([] !== $fits) {
                \usort($fits, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
                $slots[] = [$start, \array_column($fits, 1)];
            }
        }

        return $slots;
    }

    private static function in(IntervalSet $set, int $t): bool
    {
        foreach ($set->intervals() as [$from, $to]) {
            if ($from <= $t && $t < $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Slot> $slots
     * @return list<array{int, list<array{int, int, int}>}>
     */
    private static function flatten(array $slots): array
    {
        return \array_map(static fn (Slot $slot): array => [
            $slot->start,
            \array_map(static fn ($s): array => [$s->staffId, $s->end, $s->seatsLeft], $slot->staff),
        ], $slots);
    }
}
