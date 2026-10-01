<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Booking;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistEntry;
use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistStatus;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbWaitlistRepository;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * The waiting-list table as the booted plugin made it: one request per customer, service, staff and
 * day; told once; and past days given up.
 */
final class WpdbWaitlistRepositoryTest extends TestCase
{
    use RealDatabase;

    private const NOW = 1_800_000_000;

    protected function tearDown(): void
    {
        $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('waitlist'));
        parent::tearDown();
    }

    public function testAskingTwiceKeepsOneRequestWhetherOrNotAStaffMemberWasChosen(): void
    {
        $repository = new WpdbWaitlistRepository($this->realDb());

        $any = $repository->add($this->entry(7, null, '2027-01-12'), self::NOW);
        $chosen = $repository->add($this->entry(7, 3, '2027-01-12'), self::NOW);

        self::assertNotSame($any, $chosen);
        self::assertSame($any, $repository->add($this->entry(7, null, '2027-01-12'), self::NOW));
        self::assertSame($chosen, $repository->add($this->entry(7, 3, '2027-01-12'), self::NOW));
        self::assertSame(2, $repository->waitingOf(7));
    }

    public function testARequestIsToldOnce(): void
    {
        $repository = new WpdbWaitlistRepository($this->realDb());
        $id = $repository->add($this->entry(7, null, '2027-01-12'), self::NOW);

        self::assertTrue($repository->markNotified($id, self::NOW + 60));
        self::assertFalse($repository->markNotified($id, self::NOW + 120));
        self::assertSame(0, $repository->waitingOf(7));
        $page = $repository->page(0, 10);
        self::assertSame(WaitlistStatus::Notified, $page->items[0]->status);
        self::assertSame(self::NOW + 60, $page->items[0]->notifiedAt);
    }

    public function testPastDaysAreGivenUpAndOnlyComingOnesAreChecked(): void
    {
        $repository = new WpdbWaitlistRepository($this->realDb());
        $repository->add($this->entry(7, null, '2027-01-10'), self::NOW);
        $repository->add($this->entry(8, null, '2027-01-12'), self::NOW);

        self::assertSame(1, $repository->expireBefore(LocalDate::fromString('2027-01-11'), self::NOW));

        $waiting = $repository->waiting(LocalDate::fromString('2027-01-11'), 10);
        self::assertCount(1, $waiting);
        self::assertSame(8, $waiting[0]->customerId);
    }

    public function testRemovingARequestAndPaging(): void
    {
        $repository = new WpdbWaitlistRepository($this->realDb());
        $first = $repository->add($this->entry(7, null, '2027-01-12'), self::NOW);
        $second = $repository->add($this->entry(8, null, '2027-01-12'), self::NOW);

        $page = $repository->page(0, 1);
        self::assertSame(2, $page->total);
        self::assertSame($second, $page->items[0]->id);

        $repository->delete($first);
        self::assertSame(1, $repository->page(0, 10)->total);
    }

    private function entry(int $customerId, ?int $staffId, string $day): WaitlistEntry
    {
        return new WaitlistEntry(
            null,
            $customerId,
            1,
            3,
            $staffId,
            LocalDate::fromString($day),
            'https://site.test/book/'
        );
    }
}
