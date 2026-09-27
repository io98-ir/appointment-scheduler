<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\StatusChange;
use Vaqtyar\Modules\Scheduling\Contracts\Claim;

/**
 * The appointments, their extras and their history. Runs inside the
 * caller's transaction.
 */
interface AppointmentRepository
{
    /**
     * Writes the appointment, one extras row per extra line of its quote,
     * and $change to its history.
     *
     * @param ?int $userId who made the change; null for the system.
     * @param int $now UTC seconds.
     * @return int The appointment id.
     */
    public function add(Appointment $appointment, StatusChange $change, string $source, ?int $userId, int $now): int;

    /**
     * Writes one appointment_answers row per validated custom field answer
     * (T2.6). Called right after add(), never for an empty map.
     *
     * @param array<string, string> $answers by field_key, already validated.
     * @param int $now UTC seconds.
     */
    public function saveAnswers(int $appointmentId, array $answers, int $now): void;

    /**
     * Null when there is none.
     */
    public function find(int $id, bool $forUpdate = false): ?StoredAppointment;

    /**
     * Writes the appointment's new state and $change to its history.
     *
     * @param array<string, array{int|string|null, int|string|null}> $changes field => [before, after].
     */
    public function update(
        int $id,
        Appointment $appointment,
        StatusChange $change,
        Actor $actor,
        ?string $reason,
        array $changes,
        int $now,
    ): void;

    /**
     * Frees the appointment's time: deletes its occupancies.
     */
    public function release(int $id): void;

    /**
     * Takes the claimed time for the appointment, one occupancy per staff
     * member and unit, with no expiry.
     */
    public function occupy(int $id, Claim $claim, int $variantId, int $partySize): void;
}
