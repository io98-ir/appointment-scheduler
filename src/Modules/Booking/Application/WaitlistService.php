<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistEntry;
use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistRepository;
use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Modules\Customers\Contracts\CustomerDirectory;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Modules\Scheduling\Contracts\FreeStarts;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\Page;

/**
 * The waiting list: a customer who found a day full asks to be told when a time opens on it. It is a
 * notice, not a reservation (nothing is held or booked for them), so it takes no lock and cannot
 * double book: when a cancellation frees a time, whoever books it first has it.
 *
 * `check()` runs on a schedule and asks availability, the same way a customer's view does, whether
 * a day that was full now has a start; it tells each waiting customer once.
 */
final class WaitlistService
{
    public const CAPABILITY = BookingService::CAPABILITY;

    /** A customer can wait on this many days at once. */
    public const MAX_WAITING_PER_CUSTOMER = 5;

    /** The furthest day ahead one can wait for; availability itself looks no further. */
    public const MAX_DAYS_AHEAD = 366;

    private const CHECK_BATCH = 200;

    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly CustomerApi $customers,
        private readonly CustomerDirectory $directory,
        private readonly FreeStarts $starts,
        private readonly WaitlistRepository $waitlist,
        private readonly WaitlistNotifier $notifier,
        private readonly Clock $clock,
    ) {
    }

    /**
     * A guest asks to be told about a full day, by phone like a booking: the number is the one
     * proven with a code when the site requires that.
     *
     * @return int the request's id (the existing one when the same request already waits).
     * @throws InvalidValue invalid_phone, invalid_name, phone_not_verified, customer_unavailable,
     *     invalid_waitlist_date or invalid_waitlist_page.
     * @throws Conflict day_not_full when the day has a time to book, or waitlist_limit.
     * @throws NotFound when the service or the location cannot be booked.
     */
    public function join(
        string $phone,
        string $firstName,
        string $lastName,
        ?string $sessionToken,
        AvailabilityQuery $query,
        LocalDate $date,
        string $pageUrl,
    ): int {
        $today = LocalDate::fromDateTime($this->clock->now(), new \DateTimeZone('UTC'));
        // The location's today is never before UTC's, so a day before UTC's today is past everywhere.
        if ($date->isBefore($today) || $today->daysUntil($date) > self::MAX_DAYS_AHEAD) {
            throw new InvalidValue('invalid_waitlist_date', 'The day is past or too far ahead.');
        }
        if ([] !== $this->starts->startsOn($query, $date)) {
            throw new Conflict('day_not_full', 'The day has a time to book.');
        }
        $customerId = $this->customers->forBooking($phone, $firstName, $lastName, null, $sessionToken);
        if ($this->waitlist->waitingOf($customerId) >= self::MAX_WAITING_PER_CUSTOMER) {
            throw new Conflict('waitlist_limit', 'The customer already waits for the most days allowed.');
        }

        return $this->waitlist->add(
            new WaitlistEntry(
                null,
                $customerId,
                $query->locationId,
                $query->variantId,
                $query->staffId,
                $date,
                $pageUrl
            ),
            $this->clock->now()->getTimestamp()
        );
    }

    /**
     * Tells the customers whose day now has a time. A day is asked once however many wait for it;
     * a day that cannot be asked about any more (the service or location was removed) is skipped,
     * and one whose message fails stays waiting to be tried on the next run.
     *
     * @return int how many customers were told.
     */
    public function check(): int
    {
        $now = $this->clock->now()->getTimestamp();
        $today = LocalDate::fromDateTime($this->clock->now(), new \DateTimeZone('UTC'));
        $this->waitlist->expireBefore($today, $now);
        $told = 0;
        $opened = [];
        foreach ($this->waitlist->waiting($today, self::CHECK_BATCH) as $entry) {
            $key = \implode('/', [
                $entry->variantId,
                $entry->locationId,
                $entry->staffId ?? 0,
                $entry->date->toString(),
            ]);
            if (!isset($opened[$key])) {
                try {
                    $opened[$key] = $this->starts->startsOn(
                        new AvailabilityQuery($entry->variantId, $entry->locationId, $entry->staffId),
                        $entry->date
                    );
                } catch (NotFound) {
                    $opened[$key] = [];
                }
            }
            if ([] === $opened[$key] || null === $entry->id) {
                continue;
            }
            // Marked first: if the message then fails the customer is not told twice by a retry, and
            // the failure is the notifier's to log; a missed notice is better than a repeated one.
            if ($this->waitlist->markNotified($entry->id, $now)) {
                $this->notifier->slotOpened($entry, $opened[$key][0]);
                ++$told;
            }
        }

        return $told;
    }

    /**
     * @return Page<WaitlistItem>
     * @throws Forbidden
     */
    public function page(int $offset, int $limit): Page
    {
        $this->authorize();
        $page = $this->waitlist->page($offset, $limit);
        $customers = $this->directory->summaries(\array_values(\array_unique(
            \array_map(static fn (WaitlistEntry $entry): int => $entry->customerId, $page->items)
        )));

        return new Page(
            \array_map(
                static fn (WaitlistEntry $entry): WaitlistItem => new WaitlistItem(
                    $entry,
                    $customers[$entry->customerId] ?? null
                ),
                $page->items
            ),
            $page->total
        );
    }

    /**
     * @throws Forbidden
     */
    public function remove(int $id): void
    {
        $this->authorize();
        $this->waitlist->delete($id);
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }
}
