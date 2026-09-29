<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

use Vaqtyar\Modules\Booking\Contracts\AppointmentFacts;
use Vaqtyar\Modules\Booking\Contracts\AppointmentFactsReader;
use Vaqtyar\Modules\Notifications\Domain\Audience;
use Vaqtyar\Modules\Notifications\Domain\Preferences;
use Vaqtyar\Modules\Notifications\Domain\Template;
use Vaqtyar\Modules\Notifications\Domain\TemplateRenderer;
use Vaqtyar\Modules\Notifications\Domain\Trigger;
use Vaqtyar\Shared\Domain\Clock;

/**
 * Sends the messages an appointment's events call for. Every entry point is
 * a background job that can run twice or late, so it reads the appointment
 * as it is now and takes a dedup key before it sends.
 *
 * A reminder is scheduled when the appointment is booked or moved, for
 * start minus the template's offset. It is dropped when the appointment
 * moved or is no longer confirmed by then, and held until quiet hours end
 * unless that would be after the start.
 */
final class NotificationService
{
    private const CONFIRMED = 'confirmed';
    /** A booking that is over before it began has nothing to announce. */
    private const DEAD = ['cancelled', 'expired'];

    /**
     * @param array<string, NotificationChannel> $channels by id.
     * @param \Closure(): Preferences $preferences Read when a job needs it.
     */
    public function __construct(
        private readonly AppointmentFactsReader $appointments,
        private readonly TemplateRepository $templates,
        private readonly NotificationLog $log,
        private readonly ReminderScheduler $reminders,
        private readonly FactsPresenter $presenter,
        private readonly TemplateRenderer $renderer,
        private readonly Clock $clock,
        private readonly array $channels,
        private readonly \Closure $preferences,
    ) {
    }

    /**
     * An appointment was booked, cancelled or moved: sends what its
     * templates say, and schedules the reminders of a booked or moved one.
     */
    public function appointmentChanged(Trigger $trigger, int $appointmentId): void
    {
        if (Trigger::Reminder === $trigger) {
            throw new \InvalidArgumentException('A reminder is sent by remind().');
        }
        $facts = $this->appointments->find($appointmentId);
        if (null === $facts) {
            return;
        }
        // Not for an appointment that already went the other way (booked, then cancelled before the job ran).
        if (Trigger::Cancelled !== $trigger && \in_array($facts->status, self::DEAD, true)) {
            return;
        }
        foreach ($this->templates->enabledFor($trigger) as $template) {
            $this->deliver($template, $facts, $trigger);
        }
        if (Trigger::Cancelled !== $trigger) {
            $this->scheduleReminders($facts);
        }
    }

    /**
     * The scheduled time of a reminder has come.
     */
    public function remind(int $appointmentId, int $templateId, int $start): void
    {
        $facts = $this->appointments->find($appointmentId);
        $template = $this->templates->find($templateId);
        if (
            null === $facts
            || null === $template
            || !$template->enabled
            || Trigger::Reminder !== $template->trigger
            || $facts->start !== $start
            || self::CONFIRMED !== $facts->status
        ) {
            return;
        }
        $now = $this->clock->now()->getTimestamp();
        $held = ($this->preferences)()->quietHours->endsAfter($now, self::zone($facts));
        if (null !== $held && $held < $facts->start) {
            $this->reminders->schedule($held, $appointmentId, $templateId, $start);

            return;
        }
        $this->deliver($template, $facts, Trigger::Reminder);
    }

    private function scheduleReminders(AppointmentFacts $facts): void
    {
        $now = $this->clock->now()->getTimestamp();
        foreach ($this->templates->enabledFor(Trigger::Reminder) as $template) {
            $at = $facts->start - ($template->offsetMin ?? 0) * 60;
            if (null !== $template->id && $at > $now) {
                $this->reminders->schedule($at, $facts->id, $template->id, $facts->start);
            }
        }
    }

    private function deliver(Template $template, AppointmentFacts $facts, Trigger $trigger): void
    {
        $channel = $this->channels[$template->channel] ?? null;
        $recipient = null === $channel ? null : $this->recipient($template->audience, $channel->address(), $facts);
        if (null === $channel || null === $recipient || null === $template->id) {
            return;
        }
        $key = \sprintf('%s:%d:%d:%d', $trigger->value, $facts->id, $template->id, $facts->start);
        $now = $this->clock->now()->getTimestamp();
        if (!$this->log->claim($key, $template->id, $template->channel, $recipient, $now)) {
            return;
        }
        $values = $this->presenter->values($facts);
        try {
            $reference = $channel->send(new Message(
                $recipient,
                $this->renderer->render($template->subject, $values),
                $this->renderer->render($template->body, $values),
                $values,
                $template->smsPatterns
            ));
        } catch (\Throwable $e) {
            // Whatever went wrong, the claim must not stay "sending": that would block every retry.
            $this->log->markFailed($key, $e instanceof DeliveryFailed ? $e->getMessage() : $e::class);
            if (!$e instanceof DeliveryFailed) {
                throw $e;
            }

            return;
        }
        $this->log->markSent($key, $channel->id(), $reference, $this->clock->now()->getTimestamp());
    }

    /**
     * @param string $address "email" or "phone".
     */
    private function recipient(Audience $audience, string $address, AppointmentFacts $facts): ?string
    {
        $found = match ($audience) {
            Audience::Customer => 'email' === $address ? $facts->customerEmail : $facts->customerPhone,
            Audience::Staff => 'email' === $address ? $facts->staffEmail : null,
            Audience::Admin => 'email' === $address ? ($this->preferences)()->adminEmail : null,
        };

        return null === $found || '' === $found ? null : $found;
    }

    private static function zone(AppointmentFacts $facts): \DateTimeZone
    {
        try {
            return new \DateTimeZone($facts->timezone);
        } catch (\Exception) {
            return new \DateTimeZone('UTC');
        }
    }
}
