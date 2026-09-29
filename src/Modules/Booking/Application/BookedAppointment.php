<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;

/**
 * A new appointment and its id.
 */
final class BookedAppointment
{
    /**
     * @param ?string $paymentUrl where the customer pays, when the booking waits for an online payment.
     */
    public function __construct(
        public readonly int $id,
        public readonly Appointment $appointment,
        public readonly ?string $paymentUrl = null,
    ) {
    }
}
