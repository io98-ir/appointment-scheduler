<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\TransactionRunner;

/**
 * Appointments that wait for an online payment (booking-engine §7). Their
 * time is taken from the booking on; a payment moves them to confirmed, and
 * one that never comes lets them expire and frees the time. Both take the
 * appointment's row lock, so a payment and the expiry cannot both win: the
 * loser finds the appointment no longer waiting.
 */
final class UnpaidAppointments
{
    /**
     * @param \Closure(): void $changed Tells availability the occupancies changed; called after the commit.
     */
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly BookingJobs $jobs,
        private readonly TransactionRunner $transaction,
        private readonly Clock $clock,
        private readonly \Closure $changed,
    ) {
    }

    /**
     * A payment went through.
     *
     * @return bool false when the appointment is no longer waiting for it (expired or cancelled):
     *     the money is taken and staff must decide.
     */
    public function paid(int $appointmentId): bool
    {
        return $this->transaction->run(function () use ($appointmentId): bool {
            $stored = $this->appointments->find($appointmentId, true);
            if (null === $stored || AppointmentStatus::PendingPayment !== $stored->appointment->status()) {
                return false;
            }
            $now = $this->clock->now()->getTimestamp();
            // Approving by hand is not a setting yet (implementation-notes §4.12).
            $change = $stored->appointment->paid(false);
            $this->appointments->update($appointmentId, $stored->appointment, $change, Actor::system(), null, [], $now);
            $this->jobs->appointmentBooked($appointmentId);

            return true;
        });
    }

    /**
     * Gives up an appointment nobody paid for.
     *
     * @return bool false when it was no longer waiting.
     */
    public function expire(int $appointmentId): bool
    {
        $expired = $this->transaction->run(function () use ($appointmentId): bool {
            $stored = $this->appointments->find($appointmentId, true);
            if (null === $stored || AppointmentStatus::PendingPayment !== $stored->appointment->status()) {
                return false;
            }
            $change = $stored->appointment->expire();
            $this->appointments->update(
                $appointmentId,
                $stored->appointment,
                $change,
                Actor::system(),
                null,
                [],
                $this->clock->now()->getTimestamp()
            );
            $this->appointments->release($appointmentId);

            return true;
        });
        if ($expired) {
            ($this->changed)();
        }

        return $expired;
    }

    /**
     * The expiry job: appointments that have waited longer than $olderThanSeconds for their payment.
     *
     * @return int how many expired.
     */
    public function expireOlderThan(int $olderThanSeconds, int $limit): int
    {
        $expired = 0;
        $cutoff = $this->clock->now()->getTimestamp() - $olderThanSeconds;
        foreach ($this->appointments->pendingPaymentBefore($cutoff, $limit) as $id) {
            $expired += $this->expire($id) ? 1 : 0;
        }

        return $expired;
    }
}
