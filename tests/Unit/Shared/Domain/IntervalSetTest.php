<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\IntervalSet;
use Vaqtyar\Shared\Domain\InvalidValue;

final class IntervalSetTest extends TestCase
{
    public function testNormalisesToSortedDisjointIntervals(): void
    {
        $set = IntervalSet::of([[50, 60], [10, 20], [15, 30], [30, 40], [70, 70]]);

        // Overlapping and touching intervals merge; empty ones disappear.
        self::assertSame([[10, 40], [50, 60]], $set->intervals());
    }

    public function testAnEmptySetHasNoIntervals(): void
    {
        self::assertTrue(IntervalSet::empty()->isEmpty());
        self::assertTrue(IntervalSet::of([[5, 5]])->isEmpty());
        self::assertFalse(IntervalSet::of([[5, 6]])->isEmpty());
        self::assertSame([], IntervalSet::empty()->intervals());
    }

    public function testRejectsAnIntervalThatEndsBeforeItStarts(): void
    {
        $this->expectException(InvalidValue::class);

        IntervalSet::of([[10, 9]]);
    }

    public function testUnionMergesOverlappingIntervals(): void
    {
        $a = IntervalSet::of([[0, 10], [20, 30]]);
        $b = IntervalSet::of([[5, 22], [40, 50]]);

        self::assertSame([[0, 30], [40, 50]], $a->union($b)->intervals());
    }

    public function testSubtractRemovesBusyTimeFromWorkingTime(): void
    {
        // Working 09:00-17:00 minus a 12:00-13:00 break and a 16:30-18:00 appointment.
        $working = IntervalSet::of([[540, 1020]]);
        $busy = IntervalSet::of([[720, 780], [990, 1080]]);

        self::assertSame([[540, 720], [780, 990]], $working->subtract($busy)->intervals());
    }

    public function testSubtractSplitsAndRemovesWholeIntervals(): void
    {
        $set = IntervalSet::of([[0, 100], [200, 300]]);

        self::assertSame([[0, 40], [60, 100]], $set->subtract(IntervalSet::of([[40, 60], [150, 350]]))->intervals());
        self::assertSame([], $set->subtract(IntervalSet::of([[0, 300]]))->intervals());
        self::assertSame($set->intervals(), $set->subtract(IntervalSet::empty())->intervals());
    }

    public function testIntersectKeepsOnlyTheCommonParts(): void
    {
        $a = IntervalSet::of([[0, 10], [20, 30]]);
        $b = IntervalSet::of([[5, 25]]);

        self::assertSame([[5, 10], [20, 25]], $a->intersect($b)->intervals());
        self::assertSame([], $a->intersect(IntervalSet::of([[10, 20]]))->intervals(), 'Touching is not overlapping.');
    }

    public function testCoversOnlyARangeInsideOneInterval(): void
    {
        $free = IntervalSet::of([[540, 720], [780, 990]]);

        self::assertTrue($free->covers(540, 600));
        self::assertTrue($free->covers(780, 990));
        self::assertFalse($free->covers(700, 800), 'Spans the gap.');
        self::assertFalse($free->covers(500, 560));
        self::assertTrue($free->covers(600, 600), 'An empty range is always covered.');
    }

    public function testOperationsDoNotModifyTheOperands(): void
    {
        $a = IntervalSet::of([[0, 10]]);
        $b = IntervalSet::of([[5, 15]]);
        $a->union($b);
        $a->subtract($b);
        $a->intersect($b);

        self::assertSame([[0, 10]], $a->intervals());
        self::assertSame([[5, 15]], $b->intervals());
    }

    /**
     * Compares every operation with a brute-force model: a set of integer
     * points on a small domain. Fixed seeds keep failures reproducible.
     */
    public function testAgreesWithABruteForceModelOnRandomInputs(): void
    {
        for ($seed = 1; $seed <= 300; ++$seed) {
            \mt_srand($seed);
            $a = self::randomIntervals();
            $b = self::randomIntervals();
            $setA = IntervalSet::of($a);
            $setB = IntervalSet::of($b);
            $pointsA = self::points($a);
            $pointsB = self::points($b);

            self::assertSame(self::fromPoints($pointsA), $setA->intervals(), "normalise, seed $seed");
            self::assertSame(
                self::fromPoints(\array_unique([...$pointsA, ...$pointsB])),
                $setA->union($setB)->intervals(),
                "union, seed $seed"
            );
            self::assertSame(
                self::fromPoints(\array_diff($pointsA, $pointsB)),
                $setA->subtract($setB)->intervals(),
                "subtract, seed $seed"
            );
            self::assertSame(
                self::fromPoints(\array_intersect($pointsA, $pointsB)),
                $setA->intersect($setB)->intervals(),
                "intersect, seed $seed"
            );

            $start = \mt_rand(0, 60);
            $end = $start + \mt_rand(1, 20);
            $wanted = \range($start, $end - 1);
            self::assertSame(
                [] === \array_diff($wanted, $pointsA),
                $setA->covers($start, $end),
                "covers [$start, $end), seed $seed"
            );
        }
    }

    /**
     * @return list<array{int, int}>
     */
    private static function randomIntervals(): array
    {
        $intervals = [];
        $count = \mt_rand(0, 6);
        for ($i = 0; $i < $count; ++$i) {
            $start = \mt_rand(0, 80);
            $intervals[] = [$start, $start + \mt_rand(0, 15)];
        }

        return $intervals;
    }

    /**
     * @param list<array{int, int}> $intervals
     * @return list<int> Every integer point t with start <= t < end.
     */
    private static function points(array $intervals): array
    {
        $points = [];
        foreach ($intervals as [$start, $end]) {
            for ($t = $start; $t < $end; ++$t) {
                $points[$t] = $t;
            }
        }

        return \array_values($points);
    }

    /**
     * @param array<int> $points
     * @return list<array{int, int}>
     */
    private static function fromPoints(array $points): array
    {
        $points = \array_values(\array_unique($points));
        \sort($points);
        $intervals = [];
        foreach ($points as $t) {
            $last = \count($intervals) - 1;
            if ($last >= 0 && $intervals[$last][1] === $t) {
                $intervals[$last][1] = $t + 1;
            } else {
                $intervals[] = [$t, $t + 1];
            }
        }

        return $intervals;
    }
}
