<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure;

use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Modules\Notifications\Application\ReminderScheduler;

/**
 * ReminderScheduler on Action Scheduler. The arguments identify the
 * reminder, and one already waiting with the same ones is left alone, so a
 * booking job that runs twice schedules once.
 */
final class ActionSchedulerReminders implements ReminderScheduler
{
    public static function hook(): string
    {
        return Hooks::name('notifications/remind');
    }

    public function schedule(int $at, int $appointmentId, int $templateId, int $start): void
    {
        $args = ['appointment_id' => $appointmentId, 'template_id' => $templateId, 'start' => $start];
        if (\as_has_scheduled_action(self::hook(), $args)) {
            return;
        }
        \as_schedule_single_action($at, self::hook(), $args);
    }
}
