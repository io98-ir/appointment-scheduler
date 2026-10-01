<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistEntry;
use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistRepository;
use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistStatus;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\Page;

/**
 * WaitlistRepository in memory, for the service's tests.
 */
final class MemoryWaitlist implements WaitlistRepository
{
    /** @var array<int, WaitlistEntry> */
    public array $entries = [];

    private int $next = 1;

    public function add(WaitlistEntry $entry, int $now): int
    {
        foreach ($this->entries as $id => $stored) {
            if (
                WaitlistStatus::Waiting === $stored->status
                && $stored->customerId === $entry->customerId
                && $stored->variantId === $entry->variantId
                && $stored->locationId === $entry->locationId
                && $stored->staffId === $entry->staffId
                && $stored->date->equals($entry->date)
            ) {
                return $id;
            }
        }
        $id = $this->next++;
        $this->entries[$id] = self::with($entry, $id, WaitlistStatus::Waiting, null);

        return $id;
    }

    public function waitingOf(int $customerId): int
    {
        return \count(\array_filter(
            $this->entries,
            static fn (WaitlistEntry $e): bool => $e->customerId === $customerId
                && WaitlistStatus::Waiting === $e->status
        ));
    }

    /**
     * @return list<WaitlistEntry>
     */
    public function waiting(LocalDate $from, int $limit): array
    {
        return \array_slice(\array_values(\array_filter(
            $this->entries,
            static fn (WaitlistEntry $e): bool => WaitlistStatus::Waiting === $e->status && !$e->date->isBefore($from)
        )), 0, $limit);
    }

    public function markNotified(int $id, int $now): bool
    {
        $entry = $this->entries[$id] ?? null;
        if (null === $entry || WaitlistStatus::Waiting !== $entry->status) {
            return false;
        }
        $this->entries[$id] = self::with($entry, $id, WaitlistStatus::Notified, $now);

        return true;
    }

    public function expireBefore(LocalDate $today, int $now): int
    {
        $count = 0;
        foreach ($this->entries as $id => $entry) {
            if (WaitlistStatus::Waiting === $entry->status && $entry->date->isBefore($today)) {
                $this->entries[$id] = self::with($entry, $id, WaitlistStatus::Expired, null);
                ++$count;
            }
        }

        return $count;
    }

    public function page(int $offset, int $limit): Page
    {
        $newest = \array_reverse(\array_values($this->entries));

        return new Page(\array_slice($newest, $offset, $limit), \count($this->entries));
    }

    public function delete(int $id): void
    {
        unset($this->entries[$id]);
    }

    private static function with(WaitlistEntry $e, int $id, WaitlistStatus $status, ?int $notifiedAt): WaitlistEntry
    {
        return new WaitlistEntry(
            $id,
            $e->customerId,
            $e->locationId,
            $e->variantId,
            $e->staffId,
            $e->date,
            $e->pageUrl,
            $status,
            $notifiedAt,
            $e->createdAt
        );
    }
}
