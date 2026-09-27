<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;

/**
 * A new appointment and its id.
 */
final class BookedAppointment
{
    public function __construct(public readonly int $id, public readonly Appointment $appointment)
    {
    }
}
