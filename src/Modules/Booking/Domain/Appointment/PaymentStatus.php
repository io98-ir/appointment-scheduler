<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Appointment;

/**
 * What has been paid for an appointment, apart from its status
 * (booking-engine §4). Payments (M5) moves it.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case DepositPaid = 'deposit_paid';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}
