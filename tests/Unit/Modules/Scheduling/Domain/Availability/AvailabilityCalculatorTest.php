<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Scheduling\Domain\Availability;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Scheduling\Domain\Availability\AvailabilityCalculator;
use Vaqtyar\Modules\Scheduling\Domain\Availability\Occupancy;
use Vaqtyar\Modules\Scheduling\Domain\Availability\ResourceCandidate;
use Vaqtyar\Modules\Scheduling\Domain\Availability\ResourceGroup;
use Vaqtyar\Modules\Scheduling\Domain\Availability\SlotRequest;
use Vaqtyar\Modules\Scheduling\Domain\Availability\StaffCandidate;
use Vaqtyar\Modules\Scheduling\Domain\Availability\StaffChoice;
use Vaqtyar\Shared\Domain\IntervalSet;
use Vaqtyar\Tests\Unit\Modules\Catalog\Domain\AssertsInvalidValue;

/**
 * Scenario table for booking-engine §2: one variant (id 5) on 2026-10-03,
 * UTC, booked two days ahead unless a scenario says otherwise.
 */
final class AvailabilityCalculatorTest extends TestCase
{
    use AssertsInvalidValue;
    use Scenario;

    private const VARIANT = 5;

    /**
     * @return iterable<string, array{
     *     SlotRequest, list<StaffCandidate>, list<ResourceGroup>, \DateTimeImmutable, list<string>
     * }>
     */
    public static function scenarios(): iterable
    {
        yield 'working hours on the step grid' => [
            self::request(),
            [self::staff(1, ['09:00-12:00'])],
            [],
            self::now(),
            ['09:00 [1]', '09:30 [1]', '10:00 [1]', '10:30 [1]', '11:00 [1]'],
        ];
        yield 'two working ranges with a gap' => [
            self::request(),
            [self::staff(1, ['09:00-10:00', '13:00-14:30'])],
            [],
            self::now(),
            ['09:00 [1]', '13:00 [1]', '13:30 [1]'],
        ];
        yield 'an appointment is busy' => [
            self::request(),
            [self::staff(1, ['09:00-12:00'], [self::busy('10:00-11:00')])],
            [],
            self::now(),
            ['09:00 [1]', '11:00 [1]'],
        ];
        yield 'a hold of another variant is busy too' => [
            self::request(),
            [self::staff(1, ['09:00-12:00'], [self::busy('09:30-10:00', 1, 9)])],
            [],
            self::now(),
            ['10:00 [1]', '10:30 [1]', '11:00 [1]'],
        ];
        yield 'blocked time is busy' => [
            self::request(),
            [self::staff(1, ['09:00-11:00'], [], 30, ['10:00-10:30'])],
            [],
            self::now(),
            ['09:00 [1]', '09:30 [1]', '10:30 [1]'],
        ];
        yield 'the grid counts from midnight, not from the first working minute' => [
            self::request(),
            [self::staff(1, ['09:10-11:00'], [], 30)],
            [],
            self::now(),
            ['09:30 [1]', '10:00 [1]', '10:30 [1]'],
        ];
        yield 'buffers must fit in working hours and clear other bookings' => [
            self::request(before: 15, after: 15, step: 15),
            [self::staff(1, ['09:00-12:00'], [self::busy('11:30-12:00')])],
            [],
            self::now(),
            ['09:15 [1]', '09:30 [1]', '09:45 [1]', '10:00 [1]', '10:15 [1]'],
        ];
        yield 'extras lengthen the appointment' => [
            self::request(extras: 30),
            [self::staff(1, ['09:00-12:00'])],
            [],
            self::now(),
            ['09:00 [1]', '09:30 [1]', '10:00 [1]', '10:30 [1]'],
        ];
        yield 'minimum notice counts from now' => [
            self::request(minNotice: 60),
            [self::staff(1, ['09:00-12:00'])],
            [],
            self::now('09:40', '2026-10-03'),
            ['11:00 [1]'],
        ];
        yield 'the past is never offered' => [
            self::request(),
            [self::staff(1, ['09:00-12:00'])],
            [],
            self::now('10:00', '2026-10-03'),
            ['10:00 [1]', '10:30 [1]', '11:00 [1]'],
        ];
        yield 'maximum advance counts from now' => [
            self::request(maxAdvance: 10 * 60),
            [self::staff(1, ['09:00-12:00'])],
            [],
            self::now('00:00', '2026-10-03'),
            ['09:00 [1]', '09:30 [1]', '10:00 [1]'],
        ];
        yield 'a group session can be joined, but not overlapped at another start' => [
            self::request(capacity: 3),
            [self::staff(1, ['09:00-12:00'], [self::busy('10:00-11:00', 2, self::VARIANT)])],
            [],
            self::now(),
            ['09:00 [1]', '10:00 [1]', '11:00 [1]'],
        ];
        yield 'joining a session keeps the room it holds' => [
            self::request(capacity: 3),
            [self::staff(1, ['09:00-12:00'], [self::busy('10:00-11:00', 1, self::VARIANT, 1)])],
            [
                new ResourceGroup(1, [
                    self::resource(1, ['08:00-12:00'], [self::busy('10:00-11:00', 1, self::VARIANT, 1)]),
                ]),
            ],
            self::now(),
            ['09:00 [1]', '10:00 [1]', '11:00 [1]'],
        ];
        yield 'another staff member\'s session fills the room' => [
            self::request(capacity: 3),
            [
                self::staff(1, ['09:00-12:00']),
                self::staff(2, ['09:00-12:00'], [self::busy('10:00-11:00', 1, self::VARIANT, 2)]),
            ],
            [
                new ResourceGroup(1, [
                    self::resource(1, ['08:00-12:00'], [self::busy('10:00-11:00', 2, self::VARIANT, 2)]),
                ]),
            ],
            self::now(),
            ['09:00 [1,2]', '10:00 [2]', '11:00 [1,2]'],
        ];
        yield 'a party does not fit the seats left' => [
            self::request(capacity: 3, party: 2),
            [self::staff(1, ['09:00-12:00'], [self::busy('10:00-11:00', 2, self::VARIANT)])],
            [],
            self::now(),
            ['09:00 [1]', '11:00 [1]'],
        ];
        yield 'another variant blocks a group staff member' => [
            self::request(capacity: 3),
            [self::staff(1, ['09:00-12:00'], [self::busy('10:00-11:00', 1, 9)])],
            [],
            self::now(),
            ['09:00 [1]', '11:00 [1]'],
        ];
        yield 'a party larger than the capacity has no slot' => [
            self::request(capacity: 2, party: 3),
            [self::staff(1, ['09:00-12:00'])],
            [],
            self::now(),
            [],
        ];
        yield 'least busy first, then priority, then id' => [
            self::request(),
            [
                self::staff(1, ['09:00-12:00'], [self::busy('09:00-10:00')]),
                self::staff(2, ['09:00-12:00'], [], 60, [], 5),
                self::staff(3, ['09:00-12:00'], [], 60, [], 1),
            ],
            [],
            self::now(),
            ['09:00 [3,2]', '09:30 [3,2]', '10:00 [3,2,1]', '10:30 [3,2,1]', '11:00 [3,2,1]'],
        ];
        yield 'priority order ignores how busy they are' => [
            self::request(choice: StaffChoice::Priority),
            [
                self::staff(1, ['09:00-12:00'], [self::busy('09:00-10:00')], 60, [], 0),
                self::staff(2, ['09:00-12:00'], [], 60, [], 3),
            ],
            [],
            self::now(),
            ['09:00 [2]', '09:30 [2]', '10:00 [1,2]', '10:30 [1,2]', '11:00 [1,2]'],
        ];
        yield 'each staff member has their own duration' => [
            self::request(),
            [self::staff(1, ['09:00-10:00'], [], 30), self::staff(2, ['09:00-10:00'], [], 60)],
            [],
            self::now(),
            ['09:00 [1,2]', '09:30 [1]'],
        ];
        yield 'a resource of the group must be free' => [
            self::request(),
            [self::staff(1, ['09:00-12:00'])],
            [
                new ResourceGroup(1, [
                    self::resource(1, ['08:00-12:00'], [self::busy('09:00-10:00')]),
                    self::resource(2, ['08:00-12:00'], [self::busy('09:30-11:00')]),
                ]),
            ],
            self::now(),
            ['10:00 [1]', '10:30 [1]', '11:00 [1]'],
        ];
        yield 'a group needs as many free resources as its quantity' => [
            self::request(),
            [self::staff(1, ['09:00-12:00'])],
            [
                new ResourceGroup(2, [
                    self::resource(1, ['08:00-12:00'], [self::busy('09:00-10:00')]),
                    self::resource(2, ['08:00-12:00']),
                    self::resource(3, ['10:30-12:00']),
                ]),
            ],
            self::now(),
            ['10:00 [1]', '10:30 [1]', '11:00 [1]'],
        ];
        yield 'every group must fit' => [
            self::request(),
            [self::staff(1, ['09:00-12:00'])],
            [
                new ResourceGroup(1, [self::resource(1, ['08:00-12:00'])]),
                new ResourceGroup(1, [self::resource(2, ['10:00-11:00'])]),
            ],
            self::now(),
            ['10:00 [1]'],
        ];
        yield 'a resource hosts up to its capacity of sessions, of any variant' => [
            self::request(),
            [self::staff(1, ['09:00-12:00'])],
            [
                new ResourceGroup(1, [
                    self::resource(1, ['08:00-12:00'], [self::busy('10:00-11:00', 3, 9), self::busy('10:30-11:00')], 2),
                ]),
            ],
            self::now(),
            ['09:00 [1]', '09:30 [1]', '11:00 [1]'],
        ];
        yield 'a group with too few resources has no slot' => [
            self::request(),
            [self::staff(1, ['09:00-12:00'])],
            [new ResourceGroup(2, [self::resource(1, ['08:00-12:00'])])],
            self::now(),
            [],
        ];
        yield 'the resource is checked for each staff member\'s duration' => [
            self::request(),
            [self::staff(1, ['09:00-12:00'], [], 30), self::staff(2, ['09:00-12:00'], [], 60)],
            [new ResourceGroup(1, [self::resource(1, ['09:00-10:00'])])],
            self::now(),
            ['09:00 [1,2]', '09:30 [1]'],
        ];
        yield 'no staff, no slot' => [self::request(), [], [], self::now(), []];
    }

    /**
     * @dataProvider scenarios
     * @param list<StaffCandidate> $staff
     * @param list<ResourceGroup> $resources
     * @param list<string> $expected
     */
    public function testScenario(
        SlotRequest $request,
        array $staff,
        array $resources,
        \DateTimeImmutable $now,
        array $expected,
    ): void {
        self::assertSame(
            $expected,
            self::describe((new AvailabilityCalculator())->slots($request, self::day(), $now, $staff, $resources))
        );
    }

    public function testASlotKnowsEachStaffMembersEndAndSeatsLeft(): void
    {
        $slots = (new AvailabilityCalculator())->slots(
            self::request(capacity: 4, extras: 15),
            self::day(),
            self::now(),
            [
                self::staff(1, ['10:00-12:00'], [self::busy('10:00-10:45', 3, self::VARIANT)], 30),
                self::staff(2, ['10:00-12:00'], [], 60),
            ],
            []
        );

        self::assertSame(self::when('10:00'), $slots[0]->start);
        self::assertSame(
            [[2, self::when('11:15'), 4], [1, self::when('10:45'), 1]],
            \array_map(static fn ($s): array => [$s->staffId, $s->end, $s->seatsLeft], $slots[0]->staff)
        );
    }

    public function testRefusesAnInvalidRequest(): void
    {
        self::assertInvalid('invalid_party_size', static fn () => self::request(party: 0));
        self::assertInvalid('invalid_capacity', static fn () => self::request(capacity: 0));
        self::assertInvalid('invalid_step', static fn () => self::request(step: 0));
        self::assertInvalid('invalid_step', static fn () => self::request(step: 1441));
        self::assertInvalid('invalid_minutes', static fn () => self::request(extras: -1));
        self::assertInvalid('invalid_minutes', static fn () => self::request(before: -1));
        self::assertInvalid('invalid_minutes', static fn () => self::request(minNotice: -1));
        self::assertInvalid('invalid_minutes', static fn () => self::staff(1, ['09:00-10:00'], [], 0));
        self::assertInvalid('invalid_interval', static fn () => new Occupancy(10, 10));
        self::assertInvalid('invalid_seats', static fn () => new Occupancy(10, 20, 0));
        self::assertInvalid('invalid_quantity', static fn () => new ResourceGroup(0, []));
        self::assertInvalid(
            'invalid_capacity',
            static fn () => self::resource(1, ['09:00-10:00'], [], 0)
        );
    }

    private static function request(
        int $capacity = 1,
        int $party = 1,
        int $extras = 0,
        int $before = 0,
        int $after = 0,
        int $step = 30,
        int $minNotice = 0,
        int $maxAdvance = 365 * 1440,
        StaffChoice $choice = StaffChoice::LeastBusy,
    ): SlotRequest {
        return new SlotRequest(
            self::VARIANT,
            $capacity,
            $party,
            $extras,
            $before,
            $after,
            $step,
            $minNotice,
            $maxAdvance,
            $choice
        );
    }

    /**
     * @param list<string> $hours
     * @param list<Occupancy> $busy
     * @param list<string> $blocked
     */
    private static function staff(
        int $id,
        array $hours,
        array $busy = [],
        int $duration = 60,
        array $blocked = [],
        int $priority = 0,
    ): StaffCandidate {
        return new StaffCandidate(
            $id,
            $duration,
            self::hours(...$hours),
            [] === $blocked ? IntervalSet::empty() : self::hours(...$blocked),
            $busy,
            $priority
        );
    }

    /**
     * @param list<string> $hours
     * @param list<Occupancy> $busy
     */
    private static function resource(int $id, array $hours, array $busy = [], int $capacity = 1): ResourceCandidate
    {
        return new ResourceCandidate($id, $capacity, self::hours(...$hours), IntervalSet::empty(), $busy);
    }
}
