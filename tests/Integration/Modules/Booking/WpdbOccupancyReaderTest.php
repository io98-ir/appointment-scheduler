<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Booking;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Infrastructure\Migrations\CreateOccupanciesTable;
use Vaqtyar\Modules\Booking\Infrastructure\Persistence\WpdbOccupancyReader;
use Vaqtyar\Modules\Scheduling\Contracts\BusySpan;
use Vaqtyar\Tests\Fixtures\FixedClock;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * The occupancies table as the booted plugin made it, read the way
 * availability reads it.
 */
final class WpdbOccupancyReaderTest extends TestCase
{
    use RealDatabase;

    protected function tearDown(): void
    {
        $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('occupancies'));
        parent::tearDown();
    }

    public function testBootCreatedTheTableAsInnoDbWithTheOverlapIndexAndRunningItAgainKeepsIt(): void
    {
        (new CreateOccupanciesTable())->up($this->realDb());

        self::assertSame('InnoDB', $this->realDb()->getVar(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            Tables::name('occupancies')
        ));
        self::assertSame(['lock_key', 'start_at', 'end_at'], \array_column($this->realDb()->getResults(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s ORDER BY SEQ_IN_INDEX',
            Tables::name('occupancies'),
            'lock_span'
        ), 'COLUMN_NAME'));
    }

    public function testItReadsTheOverlappingSpansOfTheKeysAndSkipsExpiredHolds(): void
    {
        $this->occupy('staff:3', '09:00', '10:00', variantId: 5, staffId: 3, seats: 2);
        $this->occupy('res:3', '09:30', '10:30', variantId: 5, staffId: 3);
        $this->occupy('staff:4', '09:00', '10:00');
        $this->occupy('staff:3', '11:00', '12:00');
        $this->occupy('staff:3', '08:00', '09:00');
        // A hold that expired a second ago, and one that has not expired yet.
        $this->occupy('staff:3', '09:30', '10:30', expiresAt: '2026-10-01 09:59:59');
        $this->occupy('res:3', '09:45', '10:15', expiresAt: '2026-10-01 10:00:01');

        $reader = new WpdbOccupancyReader($this->realDb(), new FixedClock('2026-10-01 10:00:00'));
        $spans = $reader->overlapping([3], [3], self::utc('09:00'), self::utc('11:00'));
        \usort(
            $spans,
            static fn (BusySpan $a, BusySpan $b): int => [$a->onResource, $a->start] <=> [$b->onResource, $b->start]
        );

        self::assertEquals(
            [
                new BusySpan(false, 3, self::utc('09:00'), self::utc('10:00'), 2, 5, 3),
                new BusySpan(true, 3, self::utc('09:30'), self::utc('10:30'), 1, 5, 3),
                new BusySpan(true, 3, self::utc('09:45'), self::utc('10:15'), 1, null, null),
            ],
            $spans
        );
        self::assertSame([], $reader->overlapping([], [], self::utc('09:00'), self::utc('11:00')));
    }

    private function occupy(
        string $key,
        string $start,
        string $end,
        ?int $variantId = null,
        ?int $staffId = null,
        int $seats = 1,
        ?string $expiresAt = null,
    ): void {
        $this->realDb()->insert(Tables::name('occupancies'), [
            'owner_type' => null === $expiresAt ? 'appointment' : 'hold',
            'owner_id' => 1,
            'lock_key' => $key,
            'variant_id' => $variantId,
            'staff_id' => $staffId,
            'start_at' => "2026-10-01 {$start}:00",
            'end_at' => "2026-10-01 {$end}:00",
            'seats' => $seats,
            'expires_at' => $expiresAt,
        ]);
    }

    private static function utc(string $time): int
    {
        return (new \DateTimeImmutable("2026-10-01 {$time}:00", new \DateTimeZone('UTC')))->getTimestamp();
    }
}
