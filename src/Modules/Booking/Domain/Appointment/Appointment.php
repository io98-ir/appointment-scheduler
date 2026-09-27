<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Appointment;

use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Ulid;

/**
 * A booked appointment and its state machine (booking-engine §4). The
 * status moves only through the methods below, each of which returns the
 * change for appointment_history; there is no setStatus. Times are UTC
 * seconds; the price is the quote taken when the slot was held.
 */
final class Appointment
{
    /** The statuses a booking starts in, and the ones it can be cancelled from. */
    private const OPEN = [
        AppointmentStatus::Confirmed,
        AppointmentStatus::PendingApproval,
        AppointmentStatus::PendingPayment,
    ];

    private ?int $cancelledAt = null;

    private ?string $cancelReason = null;

    /**
     * @param string $localDate Y-m-d of the start in $timezone.
     */
    private function __construct(
        public readonly Ulid $uuid,
        public readonly TrackingCode $code,
        public readonly int $customerId,
        public readonly int $locationId,
        public readonly int $serviceId,
        public readonly int $variantId,
        public readonly int $staffId,
        public readonly int $start,
        public readonly int $end,
        public readonly string $localDate,
        public readonly string $timezone,
        public readonly int $partySize,
        public readonly PriceQuote $quote,
        public readonly string $customerNote,
        public readonly PaymentStatus $paymentStatus,
        private AppointmentStatus $status,
    ) {
    }

    /**
     * @param AppointmentStatus $status confirmed, or pending approval or payment.
     * @throws InvalidValue invalid_status, invalid_interval or invalid_party_size.
     */
    public static function book(
        Ulid $uuid,
        TrackingCode $code,
        int $customerId,
        int $locationId,
        int $serviceId,
        int $variantId,
        int $staffId,
        int $start,
        int $end,
        string $localDate,
        string $timezone,
        int $partySize,
        PriceQuote $quote,
        string $customerNote,
        AppointmentStatus $status,
    ): self {
        if (!\in_array($status, self::OPEN, true)) {
            throw new InvalidValue('invalid_status', 'An appointment starts confirmed or pending.');
        }
        if ($end <= $start) {
            throw new InvalidValue('invalid_interval', 'An appointment must end after it starts.');
        }
        if ($partySize < 1) {
            throw new InvalidValue('invalid_party_size', 'A party is at least one person.');
        }

        return new self(
            $uuid,
            $code,
            $customerId,
            $locationId,
            $serviceId,
            $variantId,
            $staffId,
            $start,
            $end,
            $localDate,
            $timezone,
            $partySize,
            $quote,
            $customerNote,
            PaymentStatus::Unpaid,
            $status
        );
    }

    public function status(): AppointmentStatus
    {
        return $this->status;
    }

    public function cancelledAt(): ?int
    {
        return $this->cancelledAt;
    }

    public function cancelReason(): ?string
    {
        return $this->cancelReason;
    }

    /**
     * The history entry of the creation.
     */
    public function created(): StatusChange
    {
        return new StatusChange('created', null, $this->status);
    }

    public function approve(): StatusChange
    {
        return $this->move('approve', [AppointmentStatus::PendingApproval], AppointmentStatus::Confirmed);
    }

    /**
     * @param bool $needsApproval the site approves bookings by hand, so a
     *     paid one still waits for it.
     */
    public function paid(bool $needsApproval): StatusChange
    {
        return $this->move(
            'paid',
            [AppointmentStatus::PendingPayment],
            $needsApproval ? AppointmentStatus::PendingApproval : AppointmentStatus::Confirmed
        );
    }

    public function expire(): StatusChange
    {
        return $this->move('expire', [AppointmentStatus::PendingPayment], AppointmentStatus::Expired);
    }

    public function complete(): StatusChange
    {
        return $this->move('complete', [AppointmentStatus::Confirmed], AppointmentStatus::Completed);
    }

    public function markNoShow(): StatusChange
    {
        return $this->move('no_show', [AppointmentStatus::Confirmed], AppointmentStatus::NoShow);
    }

    /**
     * @param int $now UTC seconds.
     */
    public function cancel(int $now, ?string $reason): StatusChange
    {
        $change = $this->move('cancel', self::OPEN, AppointmentStatus::Cancelled);
        $this->cancelledAt = $now;
        $this->cancelReason = $reason;

        return $change;
    }

    /**
     * @param list<AppointmentStatus> $from
     * @throws Conflict invalid_transition, and nothing changes.
     */
    private function move(string $action, array $from, AppointmentStatus $to): StatusChange
    {
        if (!\in_array($this->status, $from, true)) {
            throw new Conflict(
                'invalid_transition',
                \sprintf('An appointment cannot %s when %s.', $action, $this->status->value)
            );
        }
        $change = new StatusChange($action, $this->status, $to);
        $this->status = $to;

        return $change;
    }
}
