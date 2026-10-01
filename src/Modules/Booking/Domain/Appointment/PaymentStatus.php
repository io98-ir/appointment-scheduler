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

    /**
     * What the money paid so far says about an appointment: nothing paid is
     * unpaid whatever was refunded; refunds that take back everything are
     * refunded, any other refund partly refunded; otherwise the price is
     * paid in full or only a deposit of it is.
     *
     * @param int $total the price, in rials.
     * @param int $paid the sum of payments that went through.
     * @param int $refunded the sum of refunds recorded.
     */
    public static function resolve(int $total, int $paid, int $refunded): self
    {
        if ($paid <= 0) {
            return self::Unpaid;
        }
        if ($refunded >= $paid) {
            return self::Refunded;
        }
        if ($refunded > 0) {
            return self::PartiallyRefunded;
        }

        return $paid >= $total ? self::Paid : self::DepositPaid;
    }
}
