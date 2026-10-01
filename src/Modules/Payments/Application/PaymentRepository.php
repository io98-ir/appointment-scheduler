<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

use Vaqtyar\Modules\Payments\Domain\Payment;
use Vaqtyar\Shared\Domain\Conflict;

interface PaymentRepository
{
    /**
     * @return Payment as stored, with its id.
     * @throws Conflict authority_taken when the gateway already has this authority.
     */
    public function add(Payment $payment, int $now): Payment;

    public function find(string $gateway, string $authority, bool $forUpdate = false): ?Payment;

    public function findById(int $id, bool $forUpdate = false): ?Payment;

    /**
     * Every payment of an appointment, oldest first, whatever became of it.
     *
     * @return list<Payment>
     */
    public function forAppointment(int $appointmentId): array;

    /**
     * The sum of an appointment's payments that went through, in rials.
     */
    public function paidTotal(int $appointmentId): int;

    /**
     * Payments of an online gateway still awaiting their callback since before $cutoff, oldest first.
     *
     * @param int $cutoff UTC seconds.
     * @return list<Payment>
     */
    public function awaitingBefore(int $cutoff, int $limit): array;

    /**
     * Writes a settled payment, only if it is still awaiting its callback.
     *
     * @return bool false when another request settled it first.
     */
    public function settle(Payment $payment, int $now): bool;
}
