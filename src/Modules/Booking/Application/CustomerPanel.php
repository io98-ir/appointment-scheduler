<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\PaymentStatus;
use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Modules\Payments\Contracts\PaymentsApi;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\NotFound;

/**
 * A customer's own appointments (T4.4), for a customer who proved their
 * phone with a one-time code: the session token names the customer, and
 * everything is limited to their appointments. Cancelling and moving go
 * through AppointmentService as that customer, so its policies apply and
 * nobody else's appointment is reachable.
 */
final class CustomerPanel
{
    /** The panel shows the newest this many; older ones are for the admin. */
    public const MAX_ITEMS = 50;

    /** What can still be cancelled or moved, before its start. */
    private const ACTIVE = [
        AppointmentStatus::PendingApproval,
        AppointmentStatus::PendingPayment,
        AppointmentStatus::Confirmed,
    ];

    /**
     * @param ?PaymentsApi $payments Null when the site has no Payments, which also means no rest to pay.
     */
    public function __construct(
        private readonly CustomerApi $customers,
        private readonly AppointmentQuery $appointments,
        private readonly AppointmentService $service,
        private readonly Clock $clock,
        private readonly ?PaymentsApi $payments = null,
    ) {
    }

    /**
     * @return list<PanelAppointment> newest start first.
     * @throws Forbidden not_signed_in without a valid phone session.
     */
    public function appointments(?string $sessionToken): array
    {
        $customerId = $this->customerId($sessionToken);
        $now = $this->clock->now()->getTimestamp();
        $rows = $this->appointments->list(
            new AppointmentFilter(customerId: $customerId),
            null,
            AppointmentSort::StartDesc,
            0,
            self::MAX_ITEMS
        );
        $items = [];
        foreach ($rows as $row) {
            $decisions = \in_array($row->status, self::ACTIVE, true) && $row->start > $now
                ? $this->service->decisions($row->id, Actor::customer($customerId))
                : null;
            $items[] = new PanelAppointment(
                $row,
                $decisions['cancel'] ?? null,
                $decisions['reschedule'] ?? null,
                $this->remainder($row, $now)
            );
        }

        return $items;
    }

    /**
     * The customer pays what is left of a deposit-paid appointment, at a gateway.
     *
     * @param string $returnUrl where they land after the gateway; a page of this site.
     * @return string the gateway's page.
     * @throws Forbidden not_signed_in
     * @throws NotFound appointment_not_found, also for someone else's.
     * @throws Conflict nothing_to_pay when no rest is due, or payment_unavailable when no gateway took it.
     */
    public function payRemainder(?string $sessionToken, int $appointmentId, string $returnUrl): string
    {
        $customerId = $this->customerId($sessionToken);
        $row = $this->appointments->detail($appointmentId)?->row;
        if (null === $row || $row->customerId !== $customerId) {
            throw new NotFound('appointment_not_found', 'There is no such appointment.');
        }
        $due = $this->remainder($row, $this->clock->now()->getTimestamp());
        if (null === $due || null === $this->payments) {
            throw new Conflict('nothing_to_pay', 'There is nothing left to pay for this appointment.');
        }
        try {
            return $this->payments->startOnline($row->id, $due, $returnUrl);
        } catch (Conflict) {
            throw new Conflict('payment_unavailable', 'Online payment is not available now.');
        }
    }

    /**
     * What a customer still owes of an appointment they can come to, after paying a deposit: the
     * price less what was paid. Nothing for any other state of the money (unpaid ones are paid at
     * the place, refunded ones are for staff to settle).
     */
    private function remainder(AppointmentRow $row, int $now): ?Money
    {
        if (
            null === $this->payments
            || PaymentStatus::DepositPaid !== $row->paymentStatus
            || !\in_array($row->status, [AppointmentStatus::Confirmed, AppointmentStatus::PendingApproval], true)
            || $row->start <= $now
        ) {
            return null;
        }
        $left = $row->total->amount - $this->payments->totals($row->id)->paid;

        return $left > 0 ? Money::ofRial($left) : null;
    }

    /**
     * @throws Forbidden not_signed_in
     * @throws \Vaqtyar\Shared\Domain\NotFound appointment_not_found, also for someone else's.
     * @throws \Vaqtyar\Shared\Domain\Conflict the policy's reason code.
     */
    public function cancel(?string $sessionToken, int $appointmentId, ?string $reason): AppointmentChange
    {
        return $this->service->cancel(
            $appointmentId,
            Actor::customer($this->customerId($sessionToken)),
            $reason
        );
    }

    /**
     * @param int $start UTC seconds, an offered start.
     * @throws Forbidden not_signed_in
     * @throws \Vaqtyar\Shared\Domain\NotFound appointment_not_found, also for someone else's.
     * @throws \Vaqtyar\Shared\Domain\Conflict slot_taken or the policy's reason code.
     */
    public function reschedule(?string $sessionToken, int $appointmentId, int $start): AppointmentChange
    {
        return $this->service->reschedule(
            $appointmentId,
            Actor::customer($this->customerId($sessionToken)),
            $start
        );
    }

    private function customerId(?string $sessionToken): int
    {
        return $this->customers->customerOfSession($sessionToken) ?? throw new Forbidden('not_signed_in');
    }
}
