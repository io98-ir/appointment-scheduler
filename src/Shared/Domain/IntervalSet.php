<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * A set of points on the integer line (timestamps or minutes), stored as
 * sorted, disjoint, non-touching half-open intervals [start, end).
 *
 * Availability is Working − Busy (booking-engine §2), so subtract, intersect
 * and covers are the hot path: all are linear merges over sorted lists.
 */
final class IntervalSet
{
    /**
     * @param list<array{int, int}> $intervals Already normalised.
     */
    private function __construct(private readonly array $intervals)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param iterable<array{int, int}> $intervals In any order; may overlap or be empty.
     */
    public static function of(iterable $intervals): self
    {
        $sorted = [];
        foreach ($intervals as [$start, $end]) {
            if ($start > $end) {
                throw new InvalidValue('invalid_interval', 'An interval must not end before it starts.');
            }
            if ($start < $end) {
                $sorted[] = [$start, $end];
            }
        }
        \usort($sorted, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [];
        $last = -1;
        foreach ($sorted as [$start, $end]) {
            if ($last >= 0 && $start <= $merged[$last][1]) {
                $merged[$last][1] = \max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
                ++$last;
            }
        }

        return new self($merged);
    }

    /**
     * @return list<array{int, int}>
     */
    public function intervals(): array
    {
        return $this->intervals;
    }

    public function isEmpty(): bool
    {
        return [] === $this->intervals;
    }

    public function union(self $other): self
    {
        return self::of([...$this->intervals, ...$other->intervals]);
    }

    public function subtract(self $other): self
    {
        $result = [];
        $cuts = $other->intervals;
        $j = 0;
        $cutCount = \count($cuts);
        foreach ($this->intervals as [$start, $end]) {
            // Skip cuts that end before this interval starts; they cannot touch later ones either.
            while ($j < $cutCount && $cuts[$j][1] <= $start) {
                ++$j;
            }
            $k = $j;
            while ($k < $cutCount && $cuts[$k][0] < $end) {
                if ($cuts[$k][0] > $start) {
                    $result[] = [$start, $cuts[$k][0]];
                }
                $start = \max($start, $cuts[$k][1]);
                ++$k;
            }
            if ($start < $end) {
                $result[] = [$start, $end];
            }
        }

        return new self($result);
    }

    public function intersect(self $other): self
    {
        $result = [];
        $a = $this->intervals;
        $b = $other->intervals;
        $i = 0;
        $j = 0;
        while ($i < \count($a) && $j < \count($b)) {
            $start = \max($a[$i][0], $b[$j][0]);
            $end = \min($a[$i][1], $b[$j][1]);
            if ($start < $end) {
                $result[] = [$start, $end];
            }
            if ($a[$i][1] < $b[$j][1]) {
                ++$i;
            } else {
                ++$j;
            }
        }

        return new self($result);
    }

    /**
     * Whether every point of [start, end) is in the set. An empty range is.
     */
    public function covers(int $start, int $end): bool
    {
        if ($start >= $end) {
            return true;
        }
        foreach ($this->intervals as [$from, $to]) {
            if ($from > $start) {
                return false;
            }
            if ($to > $start) {
                return $end <= $to;
            }
        }

        return false;
    }
}
