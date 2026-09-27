<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\PaymentStatus;
use Vaqtyar\Modules\Customers\Contracts\CustomerSummary;
use Vaqtyar\Shared\Domain\Money;

/**
 * An appointment as a list or the calendar shows it, read straight from
 * its row (architecture §1), without building the entity.
 */
final class AppointmentRow
{
    /**
     * @param int $start UTC seconds.
     * @param int $end UTC seconds.
     * @param string $timezone the location's, in which start and end are shown.
     * @param ?CustomerSummary $customer null until AppointmentBrowser names them,
     *     and when the customer row is gone.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly AppointmentStatus $status,
        public readonly PaymentStatus $paymentStatus,
        public readonly int $customerId,
        public readonly int $locationId,
        public readonly int $serviceId,
        public readonly int $variantId,
        public readonly int $staffId,
        public readonly int $start,
        public readonly int $end,
        public readonly string $timezone,
        public readonly int $partySize,
        public readonly Money $total,
        public readonly ?CustomerSummary $customer = null,
    ) {
    }

    public function withCustomer(?CustomerSummary $customer): self
    {
        return new self(
            $this->id,
            $this->code,
            $this->status,
            $this->paymentStatus,
            $this->customerId,
            $this->locationId,
            $this->serviceId,
            $this->variantId,
            $this->staffId,
            $this->start,
            $this->end,
            $this->timezone,
            $this->partySize,
            $this->total,
            $customer
        );
    }
}
