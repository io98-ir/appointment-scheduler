<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

use Vaqtyar\Modules\Payments\Contracts\PaymentTotals;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;

/**
 * What was paid for an appointment and given back: the totals Booking
 * reads to keep an appointment's payment status, and the list staff see on
 * the appointment.
 */
final class PaymentLedger
{
    private const CAPABILITY = 'manage_bookings';

    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly RefundRepository $refunds,
        private readonly Authorizer $authorizer,
    ) {
    }

    /**
     * For the module's own use (PaymentsApi): no capability, since Booking calls it while it settles
     * a payment, as the gateway's callback or the customer, not as a staff member.
     */
    public function totals(int $appointmentId): PaymentTotals
    {
        return new PaymentTotals(
            $this->payments->paidTotal($appointmentId),
            $this->refunds->refundedTotalOfAppointment($appointmentId)
        );
    }

    /**
     * Every payment of the appointment, oldest first, each with what has been refunded from it.
     *
     * @return list<LedgerEntry>
     * @throws Forbidden without the booking capability.
     */
    public function entries(int $appointmentId): array
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
        $entries = [];
        foreach ($this->payments->forAppointment($appointmentId) as $payment) {
            $refunded = null === $payment->id ? 0 : $this->refunds->refundedTotal($payment->id);
            $entries[] = new LedgerEntry($payment, $refunded);
        }

        return $entries;
    }
}
