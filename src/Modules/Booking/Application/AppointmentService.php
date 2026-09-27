<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use DateTimeImmutable;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\LockKey;
use Vaqtyar\Modules\Booking\Domain\Policy\Decision;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Modules\Scheduling\Contracts\Claim;
use Vaqtyar\Modules\Scheduling\Contracts\SlotClaims;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\TransactionRunner;

/**
 * Cancel, reschedule and no-show (booking-engine §4, §6). A customer may
 * change only their own appointments and only as the service's policy
 * allows; staff with manage_bookings likewise, unless they also hold
 * override_policies and give a reason, which history keeps.
 *
 * Whatever writes occupancies locks first in its transaction (ADR-004,
 * implementation-notes §4.9): the appointment is read once outside to know
 * what to lock, then again under the locks.
 */
final class AppointmentService
{
    public const OVERRIDE_CAPABILITY = 'override_policies';

    /** A move never locks more than this span, old and new time together. */
    private const MAX_LOCK_SPAN_SECONDS = 400 * 86_400;

    /**
     * @param \Closure(): void $changed Tells availability the occupancies
     *     changed; called after the commit.
     */
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly PolicyReader $policies,
        private readonly SlotClaims $slots,
        private readonly ResourceLocker $locker,
        private readonly BookingJobs $jobs,
        private readonly TransactionRunner $transaction,
        private readonly Clock $clock,
        private readonly Authorizer $authorizer,
        private readonly \Closure $changed,
    ) {
    }

    /**
     * @param bool $override staff only: go past the policy; needs a reason.
     * @throws NotFound appointment_not_found, also for someone else's.
     * @throws Forbidden without the capabilities.
     * @throws Conflict the policy's reason code, or invalid_transition.
     */
    public function cancel(int $id, Actor $actor, ?string $reason = null, bool $override = false): AppointmentChange
    {
        $this->authorize($actor, $override, $reason);
        $found = $this->find($id, $actor);

        $work = function () use ($id, $found, $actor, $reason, $override): AppointmentChange {
            $this->locker->lock($found->lockKeys, $found->from, $found->to);
            $stored = $this->find($id, $actor, true);
            self::assertLocked($stored, $found->lockKeys, $found->from, $found->to);
            $appointment = $stored->appointment;
            $now = $this->clock->now()->getTimestamp();
            // Payments (M5) will say what was paid; until then nothing is.
            $decision = $this->policies->forService($appointment->serviceId)
                ->cancel($appointment->start, $now, Money::zero());
            $overridden = $this->check($decision, $override);

            $change = $appointment->cancel($now, $reason);
            $this->appointments->update($id, $appointment, $change, $actor, $reason, [], $now);
            $this->appointments->release($id);
            $this->jobs->appointmentCancelled($id);

            return new AppointmentChange($id, $appointment, $decision, $overridden);
        };
        $changed = $this->transaction->run($work);
        ($this->changed)();

        return $changed;
    }

    /**
     * Moves the appointment to $start, with the same staff member unless
     * $staffId names another; the price stays as booked.
     *
     * @param int $start UTC seconds, an offered start.
     * @throws NotFound appointment_not_found, also for someone else's.
     * @throws Forbidden without the capabilities.
     * @throws Conflict slot_taken, the policy's reason code, or invalid_transition.
     */
    public function reschedule(
        int $id,
        Actor $actor,
        int $start,
        ?int $staffId = null,
        ?string $reason = null,
        bool $override = false,
    ): AppointmentChange {
        $this->authorize($actor, $override, $reason);
        $found = $this->find($id, $actor);
        $old = $found->appointment;
        $query = new AvailabilityQuery(
            $old->variantId,
            $old->locationId,
            $staffId ?? $old->staffId,
            $found->extraIds,
            $old->partySize
        );
        // Outside the transaction, like a hold: what the new time could take,
        // plus what the old time holds, which the move frees.
        $scope = $this->slots->scope($query, $start);
        $keys = LockKey::sorted($scope->staffIds, $scope->resourceIds);
        $keys = \array_values(\array_unique([...$keys, ...$found->lockKeys]));
        $from = [] === $found->lockKeys ? $scope->from : \min($scope->from, $found->from);
        $to = \max($scope->to, $found->to);
        if ($to - $from > self::MAX_LOCK_SPAN_SECONDS) {
            throw new InvalidValue('reschedule_too_far', 'An appointment moves within about a year.');
        }
        $this->locker->prepare($keys, $from, $to);

        $work = function () use (
            $id,
            $actor,
            $query,
            $start,
            $keys,
            $from,
            $to,
            $reason,
            $override,
        ): AppointmentChange {
            $this->locker->lock($keys, $from, $to);
            $stored = $this->find($id, $actor, true);
            self::assertLocked($stored, $keys, $from, $to);
            $appointment = $stored->appointment;
            $now = $this->clock->now()->getTimestamp();
            $decision = $this->policies->forService($appointment->serviceId)
                ->reschedule($appointment->start, $now, $stored->rescheduled);
            $overridden = $this->check($decision, $override);

            // Its own time must not stand in the way of the move; a failed
            // claim rolls this back.
            $this->appointments->release($id);
            $claim = $this->slots->claim($query, $start);
            if (null === $claim || !self::within($claim, $keys, $from, $to)) {
                throw new Conflict('slot_taken', 'The time is no longer free.');
            }
            // In the appointment's own zone, which it keeps, so the two agree.
            $zone = new \DateTimeZone($appointment->timezone);
            [$moved, $change] = $appointment->reschedule(
                $claim->start,
                $claim->end,
                $claim->staffId,
                (new DateTimeImmutable('@' . $claim->start))->setTimezone($zone)->format('Y-m-d')
            );
            $this->appointments->update($id, $moved, $change, $actor, $reason, self::diff($appointment, $moved), $now);
            $this->appointments->occupy($id, $claim, $moved->variantId, $moved->partySize);
            $this->jobs->appointmentRescheduled($id);

            return new AppointmentChange($id, $moved, $decision, $overridden);
        };
        $changed = $this->transaction->run($work);
        ($this->changed)();

        return $changed;
    }

    /**
     * Staff record that the customer did not come. The time stays taken:
     * it has passed.
     *
     * @throws Conflict not_started before the start, or invalid_transition.
     */
    public function markNoShow(int $id, int $userId): Appointment
    {
        $actor = Actor::user($userId);
        $this->authorize($actor, false, null);

        return $this->transaction->run(function () use ($id, $actor): Appointment {
            $stored = $this->find($id, $actor, true);
            $appointment = $stored->appointment;
            $now = $this->clock->now()->getTimestamp();
            if ($now < $appointment->start) {
                throw new Conflict('not_started', 'A no-show is recorded once the appointment has started.');
            }
            $change = $appointment->markNoShow();
            $this->appointments->update($id, $appointment, $change, $actor, null, [], $now);

            return $appointment;
        });
    }

    private function authorize(Actor $actor, bool $override, ?string $reason): void
    {
        if ($actor->isCustomer()) {
            if ($override) {
                throw new Forbidden(self::OVERRIDE_CAPABILITY);
            }

            return;
        }
        if (!$this->authorizer->allows(BookingService::CAPABILITY)) {
            throw new Forbidden(BookingService::CAPABILITY);
        }
        if ($override && !$this->authorizer->allows(self::OVERRIDE_CAPABILITY)) {
            throw new Forbidden(self::OVERRIDE_CAPABILITY);
        }
        if ($override && '' === \trim((string) $reason)) {
            throw new InvalidValue('reason_required', 'Going past a policy needs a reason.');
        }
    }

    /**
     * A customer's own only: someone else's is as good as missing.
     */
    private function find(int $id, Actor $actor, bool $forUpdate = false): StoredAppointment
    {
        $stored = $this->appointments->find($id, $forUpdate);
        if (null === $stored || ($actor->isCustomer() && $stored->appointment->customerId !== $actor->id)) {
            throw new NotFound('appointment_not_found', 'There is no such appointment.');
        }

        return $stored;
    }

    /**
     * @return bool Whether the policy was overridden.
     * @throws Conflict the decision's reason code, unless overridden.
     */
    private function check(Decision $decision, bool $override): bool
    {
        if ($decision->allowed) {
            return false;
        }
        if (!$override) {
            throw new Conflict((string) $decision->reasonCode, 'The policy does not allow this change now.');
        }

        return true;
    }

    /**
     * The appointment may have moved between the read outside the
     * transaction and the locks; then its occupancies are not the ones
     * locked, and writing them would break ADR-004. The client tries again.
     *
     * @param list<string> $keys
     * @throws Conflict appointment_changed
     */
    private static function assertLocked(StoredAppointment $stored, array $keys, int $from, int $to): void
    {
        $outside = [] !== \array_diff($stored->lockKeys, $keys);
        if ($outside || ([] !== $stored->lockKeys && ($stored->from < $from || $stored->to > $to))) {
            throw new Conflict('appointment_changed', 'The appointment changed meanwhile; try again.');
        }
    }

    /**
     * Whether the claim takes only calendars and days that were locked:
     * the catalog may have changed between the two reads.
     *
     * @param list<string> $keys
     */
    private static function within(Claim $claim, array $keys, int $from, int $to): bool
    {
        $taken = LockKey::sorted([$claim->staffId], $claim->resourceIds);

        return [] === \array_diff($taken, $keys) && $claim->from >= $from && $claim->to <= $to;
    }

    /**
     * @return array<string, array{int|string|null, int|string|null}>
     */
    private static function diff(Appointment $before, Appointment $after): array
    {
        $pairs = [
            'start' => [$before->start, $after->start],
            'end' => [$before->end, $after->end],
            'staff_id' => [$before->staffId, $after->staffId],
        ];

        return \array_filter($pairs, static fn (array $pair): bool => $pair[0] !== $pair[1]);
    }
}
