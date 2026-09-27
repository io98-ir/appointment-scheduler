<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Appointment;

/**
 * Where an appointment is in its life (booking-engine §4). A custom state
 * such as "waiting for documents" is a label, not a status.
 */
enum AppointmentStatus: string
{
    case PendingApproval = 'pending_approval';
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
