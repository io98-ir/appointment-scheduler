<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Policy\Decision;
use Vaqtyar\Shared\Domain\Money;

/**
 * One of a customer's appointments in the panel, with what its policy
 * says about cancelling and moving it; both null once it can no longer be
 * changed (it started, was cancelled, completed or expired). $due is what is
 * left to pay online when only a deposit was paid, null when nothing is.
 */
final class PanelAppointment
{
    public function __construct(
        public readonly AppointmentRow $row,
        public readonly ?Decision $cancel,
        public readonly ?Decision $reschedule,
        public readonly ?Money $due = null,
    ) {
    }
}
