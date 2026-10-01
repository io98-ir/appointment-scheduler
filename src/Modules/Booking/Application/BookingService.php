<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use DateTimeImmutable;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\TrackingCode;
use Vaqtyar\Modules\Booking\Domain\Field\AnswerValidator;
use Vaqtyar\Modules\Booking\Domain\HoldToken;
use Vaqtyar\Modules\Booking\Domain\Policy\BookingTerms;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceLine;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Shared\Domain\Ulid;

/**
 * Turns a hold into an appointment (booking-engine §3). In one transaction:
 * the hold's calendars are locked first (ADR-004, implementation-notes
 * §4.9), the hold is read again under the locks, its coupon is checked
 * again and its use counted, the appointment is written, the hold's
 * occupancies become the appointment's, and the notification job is
 * queued (ADR-005). The time is never free in between. Custom field
 * answers are validated against the service's fields (T2.6) inside the
 * same transaction, since only there is the service known.
 *
 * Staff book this way for now; the customer's own booking, with its
 * identity, comes with the widget (T4.2, T4.3).
 */
final class BookingService
{
    public const CAPABILITY = 'manage_bookings';

    /** appointments.source of a booking made in the admin. */
    public const SOURCE_ADMIN = 'admin';

    /** appointments.source of a guest's booking from the widget. */
    public const SOURCE_WIDGET = 'widget';

    /**
     * @param \Closure(): void $changed Tells availability the occupancies
     *     changed; called after the commit.
     * @param ?OnlineCheckout $checkout Null when the site has no Payments to pay with.
     * @param ?TermsReader $terms What a service asks of a customer's own booking; null asks nothing.
     */
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly CustomerApi $customers,
        private readonly PricingReader $pricing,
        private readonly FieldReader $fields,
        private readonly ResourceLocker $locker,
        private readonly HoldRepository $holds,
        private readonly AppointmentRepository $appointments,
        private readonly BookingJobs $jobs,
        private readonly TransactionRunner $transaction,
        private readonly Clock $clock,
        private readonly Authorizer $authorizer,
        private readonly \Closure $changed,
        private readonly ?OnlineCheckout $checkout = null,
        private readonly ?TermsReader $terms = null,
    ) {
    }

    /**
     * @param int $userId the staff member's WordPress user.
     * @param array<string, mixed> $answers custom field answers by field_key, as the client sent them.
     * @throws Forbidden without the capability.
     * @throws NotFound hold_not_found when the token is unknown, expired or
     *     already confirmed.
     * @throws Conflict service_unavailable when the service or location is
     *     gone since the hold.
     * @throws InvalidValue customer_unavailable when the customer is unknown,
     *     deleted or blocked; why the hold's coupon cannot be used any more,
     *     or an answer that does not fit its field.
     */
    public function confirm(
        HoldToken $token,
        int $customerId,
        string $customerNote,
        int $userId,
        array $answers = [],
    ): BookedAppointment {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }

        return $this->book($token, $customerId, $customerNote, $userId, self::SOURCE_ADMIN, $answers);
    }

    /**
     * A guest's own booking from the widget (T4.2): no capability, since the
     * hold token is what the guest holds. The customer is found by phone or
     * made (CustomerApi::forBooking); T4.3 will require the phone to be
     * verified first. A customer made for a booking that then fails (the hold
     * expired) stays, like one a staff member created and never booked.
     *
     * @param array<string, mixed> $answers custom field answers by field_key.
     * @param ?string $payReturnUrl Set when the customer pays online: the appointment then waits for the
     *     payment (a free one does not), and the result says where to pay. The page they return to.
     * @throws NotFound hold_not_found, or Conflict / InvalidValue as confirm().
     * @throws Conflict payment_unavailable when no gateway took the payment; nothing is booked then.
     */
    public function confirmAsGuest(
        HoldToken $token,
        string $phone,
        string $firstName,
        string $lastName,
        ?string $email,
        string $customerNote,
        array $answers = [],
        ?string $sessionToken = null,
        ?string $payReturnUrl = null,
    ): BookedAppointment {
        $customerId = $this->customers->forBooking($phone, $firstName, $lastName, $email, $sessionToken);
        $online = null !== $payReturnUrl && null !== $this->checkout && $this->checkout->available();
        $booked = $this->book($token, $customerId, $customerNote, null, self::SOURCE_WIDGET, $answers, $online);
        if (AppointmentStatus::PendingPayment !== $booked->appointment->status() || null === $this->checkout) {
            return $booked;
        }

        return new BookedAppointment(
            $booked->id,
            $booked->appointment,
            $this->checkout->start($booked, (string) $payReturnUrl),
            $booked->dueNow
        );
    }

    /**
     * @param array<string, mixed> $answers
     */
    private function book(
        HoldToken $token,
        int $customerId,
        string $customerNote,
        ?int $userId,
        string $source,
        array $answers,
        bool $online = false,
    ): BookedAppointment {
        // Not under a lock: a customer deleted or blocked a moment later
        // keeps this one appointment, as one booked a moment earlier would.
        if (!$this->customers->canBook($customerId)) {
            throw new InvalidValue('customer_unavailable', 'The customer does not exist or is blocked.');
        }
        // Outside the transaction, only to learn what to lock: a read in it
        // before the locks would fix a stale snapshot (REPEATABLE READ).
        $found = $this->holds->find($token->hash());
        if (null === $found) {
            throw self::notFound();
        }

        $work = function () use (
            $found,
            $token,
            $customerId,
            $customerNote,
            $userId,
            $source,
            $answers,
            $online
        ): BookedAppointment {
            $this->locker->lock($found->lockKeys, $found->from, $found->to);
            $hold = $this->holds->find($token->hash(), true);
            $now = $this->clock->now();
            if (null === $hold || $hold->expiresAt <= $now->getTimestamp()) {
                throw self::notFound();
            }
            $held = $this->holds->details($hold->id);
            $offer = $this->catalog->offer($held->variantId);
            $location = $this->catalog->location($held->locationId);
            if (null === $offer || null === $location) {
                throw new Conflict('service_unavailable', 'The service is no longer offered.');
            }
            $this->countCoupon($held, $offer->serviceId, $now->getTimestamp());
            $validAnswers = AnswerValidator::validate($this->fields->forService($offer->serviceId), $answers);
            // Only the customer's own booking is held to the service's terms; staff book without paying
            // and their bookings are not held back for approval.
            $terms = self::SOURCE_WIDGET === $source
                ? ($this->terms?->termsFor($offer->serviceId) ?? BookingTerms::lenient())
                : BookingTerms::lenient();
            $total = $held->quote->total();
            if ($terms->deposit->required && $total->amount > 0 && !$online) {
                throw new InvalidValue('payment_required', 'This service has to be paid online to be booked.');
            }
            // A free booking has nothing to pay. One that waits for its payment is confirmed (or
            // handed to staff to approve) when it is paid; the others at once.
            $payable = $online && $total->amount > 0;
            $status = match (true) {
                $payable => AppointmentStatus::PendingPayment,
                $terms->approval->required => AppointmentStatus::PendingApproval,
                default => AppointmentStatus::Confirmed,
            };

            $appointment = Appointment::book(
                Ulid::fromParts($now, \random_bytes(10)),
                TrackingCode::generate(),
                $customerId,
                $held->locationId,
                $offer->serviceId,
                $held->variantId,
                $held->staffId,
                $held->start,
                $held->end,
                (new DateTimeImmutable('@' . $held->start))->setTimezone($location->timezone)->format('Y-m-d'),
                $location->timezone->getName(),
                $held->partySize,
                $held->quote,
                $customerNote,
                $status
            );
            $id = $this->appointments->add(
                $appointment,
                $appointment->created(),
                $source,
                $userId,
                $now->getTimestamp()
            );
            if ([] !== $validAnswers) {
                $this->appointments->saveAnswers($id, $validAnswers, $now->getTimestamp());
            }
            $this->holds->handOver($hold->id, $id);
            if (AppointmentStatus::PendingPayment !== $status) {
                // A booking that waits for its payment is announced when it is paid.
                $this->jobs->appointmentBooked($id);
            }

            return new BookedAppointment($id, $appointment, null, $payable ? $terms->deposit->dueNow($total) : null);
        };
        $booked = $this->transaction->run($work);
        ($this->changed)();

        return $booked;
    }

    /**
     * The hold checked its coupon but did not use it up; the booking does,
     * under the coupon's row lock, so two bookings cannot share a last use.
     */
    private function countCoupon(HeldBooking $held, int $serviceId, int $now): void
    {
        $couponId = $held->quote->lineOf(PriceLine::COUPON)?->ref;
        if (null === $couponId) {
            return;
        }
        $coupon = $this->pricing->couponForUse($couponId)
            ?? throw new InvalidValue('coupon_not_found', 'There is no coupon with this code.');
        $coupon->assertUsable($serviceId, $now);
        $this->pricing->countUse($couponId);
    }

    private static function notFound(): NotFound
    {
        return new NotFound('hold_not_found', 'The hold does not exist or has expired.');
    }
}
