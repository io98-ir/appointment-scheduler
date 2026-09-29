<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Payments\Contracts\PaymentsApi;
use Vaqtyar\Shared\Domain\Conflict;

/**
 * Sends a booking's customer to pay (booking-engine §7). If no gateway takes
 * the payment the appointment is given up at once, not held for the timeout.
 */
final class OnlineCheckout
{
    public function __construct(private readonly PaymentsApi $payments, private readonly UnpaidAppointments $unpaid)
    {
    }

    public function available(): bool
    {
        return $this->payments->onlineAvailable();
    }

    /**
     * @return string the gateway's page.
     * @throws Conflict payment_unavailable when no gateway could take it.
     */
    public function start(BookedAppointment $booked, string $returnUrl): string
    {
        try {
            return $this->payments->startOnline($booked->id, $booked->appointment->quote->total(), $returnUrl);
        } catch (Conflict) {
            $this->unpaid->expire($booked->id);

            throw new Conflict('payment_unavailable', 'Online payment is not available now; the booking was not made.');
        }
    }
}
