<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use DateTimeImmutable;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\TrackingCode;
use Vaqtyar\Modules\Booking\Domain\Field\AnswerValidator;
use Vaqtyar\Modules\Booking\Domain\HoldToken;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceLine;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
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

    /**
     * @param \Closure(): void $changed Tells availability the occupancies
     *     changed; called after the commit.
     */
    public function __construct(
        private readonly CatalogApi $catalog,
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
     * @throws InvalidValue why the hold's coupon cannot be used any more, or an answer that does not fit its field.
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
        // Outside the transaction, only to learn what to lock: a read in it
        // before the locks would fix a stale snapshot (REPEATABLE READ).
        $found = $this->holds->find($token->hash());
        if (null === $found) {
            throw self::notFound();
        }

        $work = function () use ($found, $token, $customerId, $customerNote, $userId, $answers): BookedAppointment {
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
                AppointmentStatus::Confirmed
            );
            $id = $this->appointments->add(
                $appointment,
                $appointment->created(),
                self::SOURCE_ADMIN,
                $userId,
                $now->getTimestamp()
            );
            if ([] !== $validAnswers) {
                $this->appointments->saveAnswers($id, $validAnswers, $now->getTimestamp());
            }
            $this->holds->handOver($hold->id, $id);
            $this->jobs->appointmentBooked($id);

            return new BookedAppointment($id, $appointment);
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
