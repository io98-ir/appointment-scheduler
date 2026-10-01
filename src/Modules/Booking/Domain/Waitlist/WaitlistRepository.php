<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Waitlist;

use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\Page;

/**
 * Where waiting-list requests are kept.
 */
interface WaitlistRepository
{
    /**
     * Stores the request, or finds the same one still waiting (same customer, service, location,
     * staff and day) and keeps it, so asking twice is one request.
     *
     * @return int the id.
     */
    public function add(WaitlistEntry $entry, int $now): int;

    /**
     * How many requests of the customer still wait.
     */
    public function waitingOf(int $customerId): int;

    /**
     * The requests still waiting for a day from $from on, oldest first.
     *
     * @return list<WaitlistEntry>
     */
    public function waiting(LocalDate $from, int $limit): array;

    /**
     * Marks the request told. Only a waiting one changes: false when it had already been handled.
     */
    public function markNotified(int $id, int $now): bool;

    /**
     * Gives up the waiting requests for days before $today.
     *
     * @return int how many.
     */
    public function expireBefore(LocalDate $today, int $now): int;

    /**
     * @return Page<WaitlistEntry> newest first.
     */
    public function page(int $offset, int $limit): Page;

    public function delete(int $id): void;
}
