<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Policy\Decision;
use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Forbidden;

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

    public function __construct(
        private readonly CustomerApi $customers,
        private readonly AppointmentQuery $appointments,
        private readonly AppointmentService $service,
        private readonly Clock $clock,
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
            $items[] = new PanelAppointment($row, $decisions['cancel'] ?? null, $decisions['reschedule'] ?? null);
        }

        return $items;
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
