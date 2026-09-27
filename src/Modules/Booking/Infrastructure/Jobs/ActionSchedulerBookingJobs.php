<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Jobs;

use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Modules\Booking\Application\BookingJobs;

/**
 * BookingJobs on Action Scheduler, whose tables share the booking's
 * connection and transaction, so a rolled-back booking leaves no job
 * (ADR-005). Notifications (M5) listen to the hooks and dedupe their sends.
 */
final class ActionSchedulerBookingJobs implements BookingJobs
{
    public function appointmentBooked(int $appointmentId): void
    {
        // Not "unique": Action Scheduler compares the hook only, not the
        // arguments, so it would drop the job of a second appointment.
        \as_enqueue_async_action(Hooks::name('booking/appointment_booked'), ['appointment_id' => $appointmentId]);
    }
}
