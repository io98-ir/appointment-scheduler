<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Contracts;

/**
 * What other modules may read of an appointment. No capability is checked:
 * the callers are background jobs that act for the site.
 */
interface AppointmentFactsReader
{
    /**
     * Null when there is no such appointment.
     */
    public function find(int $appointmentId): ?AppointmentFacts;
}
