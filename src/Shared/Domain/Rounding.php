<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * How a fraction of the smallest currency unit is rounded. Every calculation
 * that divides money names one (booking-engine §4).
 */
enum Rounding
{
    /** Toward zero: 10.9 → 10, -10.9 → -10. */
    case Down;

    /** Away from zero: 10.1 → 11, -10.1 → -11. */
    case Up;

    /** To the nearest, a half away from zero: 10.5 → 11, 10.4 → 10, -10.5 → -11. */
    case HalfUp;
}
