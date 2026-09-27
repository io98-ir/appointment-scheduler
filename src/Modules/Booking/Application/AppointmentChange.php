<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Policy\Decision;

/**
 * A changed appointment and what its policy said about the change.
 */
final class AppointmentChange
{
    /**
     * @param bool $overridden staff went past a policy that did not allow it.
     */
    public function __construct(
        public readonly int $id,
        public readonly Appointment $appointment,
        public readonly Decision $decision,
        public readonly bool $overridden,
    ) {
    }
}
