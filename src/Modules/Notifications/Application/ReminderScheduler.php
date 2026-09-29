<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

/**
 * Runs a reminder at a time (Action Scheduler). Scheduling the same reminder
 * twice must leave one.
 */
interface ReminderScheduler
{
    /**
     * @param int $at UTC seconds.
     * @param int $start the appointment's start when scheduled: the reminder
     *     is dropped if the appointment moved since.
     */
    public function schedule(int $at, int $appointmentId, int $templateId, int $start): void;
}
