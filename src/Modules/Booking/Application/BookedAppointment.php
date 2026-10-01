<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Shared\Domain\Money;

/**
 * A new appointment and its id.
 */
final class BookedAppointment
{
    /**
     * @param ?string $paymentUrl where the customer pays, when the booking waits for an online payment.
     * @param ?Money $dueNow what the customer is asked to pay online now (a deposit may be only part of
     *     the price); null when the booking waits for no payment.
     */
    public function __construct(
        public readonly int $id,
        public readonly Appointment $appointment,
        public readonly ?string $paymentUrl = null,
        public readonly ?Money $dueNow = null,
    ) {
    }
}
