<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\StatusChange;

/**
 * The appointments, their extras and their history. Runs inside the
 * caller's transaction.
 */
interface AppointmentRepository
{
    /**
     * Writes the appointment, one extras row per extra line of its quote,
     * and $change to its history.
     *
     * @param ?int $userId who made the change; null for the system.
     * @param int $now UTC seconds.
     * @return int The appointment id.
     */
    public function add(Appointment $appointment, StatusChange $change, string $source, ?int $userId, int $now): int;
}
