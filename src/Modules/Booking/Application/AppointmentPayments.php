<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\PaymentStatus;
use Vaqtyar\Modules\Payments\Contracts\PaymentsApi;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\TransactionRunner;

/**
 * Keeps an appointment's payment status true to the money (booking-engine §7).
 * Payments says a payment went through or a refund was recorded; this reads
 * what has been paid and refunded and writes the status that follows
 * (PaymentStatus::resolve). A booking that waits for its payment is
 * confirmed by it, or handed to staff to approve when its service asks for
 * that. Everything runs under the appointment's row lock, so the totals and
 * the status are written together, and a payment racing the expiry has
 * exactly one winner (UnpaidAppointments::expire takes the same lock).
 */
final class AppointmentPayments
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly PaymentsApi $payments,
        private readonly TermsReader $terms,
        private readonly BookingJobs $jobs,
        private readonly TransactionRunner $transaction,
        private readonly Clock $clock,
    ) {
    }

    /**
     * A payment went through.
     *
     * @return bool false when nothing could be applied: the appointment is gone, or was cancelled or
     *     expired meanwhile (the money is taken and staff must decide), or no money is on record.
     */
    public function received(int $appointmentId): bool
    {
        return $this->transaction->run(function () use ($appointmentId): bool {
            $stored = $this->appointments->find($appointmentId, true);
            if (null === $stored) {
                return false;
            }
            $appointment = $stored->appointment;
            $status = $appointment->status();
            if (AppointmentStatus::Cancelled === $status || AppointmentStatus::Expired === $status) {
                return false;
            }
            $paymentStatus = $this->resolved($appointmentId, $appointment->quote->total()->amount);
            if (PaymentStatus::Unpaid === $paymentStatus) {
                return false;
            }
            $now = $this->clock->now()->getTimestamp();
            if (AppointmentStatus::PendingPayment === $status) {
                $needsApproval = $this->terms->termsFor($appointment->serviceId)->approval->required;
                $change = $appointment->paid($needsApproval, $paymentStatus);
                $this->appointments->update($appointmentId, $appointment, $change, Actor::system(), null, [], $now);
                $this->jobs->appointmentBooked($appointmentId);

                return true;
            }
            $this->follow($appointmentId, $stored, $paymentStatus, $now);

            return true;
        });
    }

    /**
     * What has been paid or refunded changed (a refund, money recorded by staff): the payment status
     * follows, in whatever status the appointment is, since a refund may come after a cancellation.
     */
    public function changed(int $appointmentId): void
    {
        $this->transaction->run(function () use ($appointmentId): void {
            $stored = $this->appointments->find($appointmentId, true);
            if (null === $stored) {
                return;
            }
            $this->follow(
                $appointmentId,
                $stored,
                $this->resolved($appointmentId, $stored->appointment->quote->total()->amount),
                $this->clock->now()->getTimestamp()
            );
        });
    }

    private function resolved(int $appointmentId, int $total): PaymentStatus
    {
        $totals = $this->payments->totals($appointmentId);

        return PaymentStatus::resolve($total, $totals->paid, $totals->refunded);
    }

    private function follow(int $appointmentId, StoredAppointment $stored, PaymentStatus $paymentStatus, int $now): void
    {
        $appointment = $stored->appointment;
        $before = $appointment->paymentStatus();
        if ($before === $paymentStatus) {
            return;
        }
        $change = $appointment->recordPayment($paymentStatus);
        $this->appointments->update(
            $appointmentId,
            $appointment,
            $change,
            Actor::system(),
            null,
            ['payment_status' => [$before->value, $paymentStatus->value]],
            $now
        );
    }
}
