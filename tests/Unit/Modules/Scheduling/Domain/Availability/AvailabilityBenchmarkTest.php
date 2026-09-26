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

/**
 * The budget of booking-engine §2: 10 staff × 1 day under 50 ms, here on a
 * busy day (5-minute grid, a break, 12 bookings each, a resource group).
 * The best of five runs, so a slow CI neighbour does not fail it.
 */
final class AvailabilityBenchmarkTest extends TestCase
{
    use Scenario;

    public function testTenStaffForOneDayUnderFiftyMilliseconds(): void
    {
        $staff = [];
        for ($id = 1; $id <= 10; ++$id) {
            $busy = [];
            for ($i = 0; $i < 12; ++$i) {
                $start = self::when('08:00') + ($i * 50 + $id * 5) * 60;
                $busy[] = new Occupancy($start, $start + 30 * 60, 1, 0 === $i % 3 ? 9 : 5);
            }
            $staff[] = new StaffCandidate(
                $id,
                30 + ($id % 3) * 15,
                self::hours('08:00-13:00', '14:00-20:00'),
                self::hours('16:00-16:15'),
                $busy,
                $id % 4
            );
        }
        $rooms = [];
        for ($id = 1; $id <= 5; ++$id) {
            $rooms[] = new ResourceCandidate($id, 1, self::hours('08:00-20:00'), IntervalSet::empty(), [
                self::busy('10:00-11:00'),
                self::busy('15:00-15:30'),
            ]);
        }
        $request = new SlotRequest(5, 2, 1, 15, 5, 10, 5, 0, 365 * 1440, StaffChoice::LeastBusy);
        $calculator = new AvailabilityCalculator();

        $best = \INF;
        $slots = [];
        for ($run = 0; $run < 5; ++$run) {
            $started = \hrtime(true);
            $slots = $calculator->slots($request, self::day(), self::now(), $staff, [new ResourceGroup(1, $rooms)]);
            $best = \min($best, (\hrtime(true) - $started) / 1e6);
        }

        self::assertNotEmpty($slots);
        self::assertLessThan(50.0, $best, \sprintf('Took %.1f ms.', $best));
    }
}
