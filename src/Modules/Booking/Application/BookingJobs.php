<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

/**
 * The critical jobs a booking starts, queued inside its transaction so
 * they exist exactly when it commits (ADR-005). Each job is idempotent.
 */
interface BookingJobs
{
    /**
     * Tells the customer and the staff about a new appointment (M5 sends it).
     */
    public function appointmentBooked(int $appointmentId): void;

    public function appointmentCancelled(int $appointmentId): void;

    public function appointmentRescheduled(int $appointmentId): void;
}
