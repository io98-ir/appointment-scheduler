<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistEntry;
use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistRepository;
use Vaqtyar\Modules\Booking\Domain\Waitlist\WaitlistStatus;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\Page;

/**
 * WaitlistRepository over the waitlist table. A row that no longer parses (a page address edited by
 * hand, an unknown status) is skipped from a read, as the other repositories do.
 */
final class WpdbWaitlistRepository implements WaitlistRepository
{
    public function __construct(private readonly Db $db)
    {
    }

    public function add(WaitlistEntry $entry, int $now): int
    {
        $table = Tables::name('waitlist');
        // "Any staff member" is NULL, which only IS NULL matches.
        $staff = null === $entry->staffId ? 'staff_id IS NULL' : 'staff_id = %d';
        $same = $this->db->getVar(
            'SELECT id FROM %i WHERE customer_id = %d AND variant_id = %d AND location_id = %d'
            . ' AND wanted_date = %s AND status = %s AND ' . $staff . ' LIMIT 1',
            $table,
            $entry->customerId,
            $entry->variantId,
            $entry->locationId,
            $entry->date->toString(),
            WaitlistStatus::Waiting->value,
            ...(null === $entry->staffId ? [] : [$entry->staffId])
        );
        if (null !== $same) {
            return (int) $same;
        }
        $stamp = self::utc($now);

        return $this->db->insert($table, [
            'customer_id' => $entry->customerId,
            'location_id' => $entry->locationId,
            'variant_id' => $entry->variantId,
            'staff_id' => $entry->staffId,
            'wanted_date' => $entry->date->toString(),
            'page_url' => $entry->pageUrl,
            'status' => WaitlistStatus::Waiting->value,
            'created_at' => $stamp,
            'updated_at' => $stamp,
        ]);
    }

    public function waitingOf(int $customerId): int
    {
        return (int) $this->db->getVar(
            'SELECT COUNT(*) FROM %i WHERE customer_id = %d AND status = %s',
            Tables::name('waitlist'),
            $customerId,
            WaitlistStatus::Waiting->value
        );
    }

    /**
     * @return list<WaitlistEntry>
     */
    public function waiting(LocalDate $from, int $limit): array
    {
        $rows = $this->db->getResults(
            'SELECT * FROM %i WHERE status = %s AND wanted_date >= %s ORDER BY id ASC LIMIT %d',
            Tables::name('waitlist'),
            WaitlistStatus::Waiting->value,
            $from->toString(),
            $limit
        );

        return self::entries($rows);
    }

    public function markNotified(int $id, int $now): bool
    {
        $stamp = self::utc($now);

        return 1 === $this->db->execute(
            'UPDATE %i SET status = %s, notified_at = %s, updated_at = %s WHERE id = %d AND status = %s',
            Tables::name('waitlist'),
            WaitlistStatus::Notified->value,
            $stamp,
            $stamp,
            $id,
            WaitlistStatus::Waiting->value
        );
    }

    public function expireBefore(LocalDate $today, int $now): int
    {
        return $this->db->execute(
            'UPDATE %i SET status = %s, updated_at = %s WHERE status = %s AND wanted_date < %s',
            Tables::name('waitlist'),
            WaitlistStatus::Expired->value,
            self::utc($now),
            WaitlistStatus::Waiting->value,
            $today->toString()
        );
    }

    public function page(int $offset, int $limit): Page
    {
        $table = Tables::name('waitlist');
        $rows = $this->db->getResults('SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', $table, $limit, $offset);

        return new Page(self::entries($rows), (int) $this->db->getVar('SELECT COUNT(*) FROM %i', $table));
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM %i WHERE id = %d', Tables::name('waitlist'), $id);
    }

    /**
     * @param list<array<string, string|null>> $rows
     * @return list<WaitlistEntry>
     */
    private static function entries(array $rows): array
    {
        $entries = [];
        foreach ($rows as $values) {
            $row = new Row($values);
            $status = WaitlistStatus::tryFrom($row->string('status'));
            if (null === $status) {
                continue;
            }
            try {
                $entries[] = new WaitlistEntry(
                    $row->int('id'),
                    $row->int('customer_id'),
                    $row->int('location_id'),
                    $row->int('variant_id'),
                    $row->intOrNull('staff_id'),
                    LocalDate::fromString($row->string('wanted_date')),
                    $row->string('page_url'),
                    $status,
                    self::timestamp($row->stringOrNull('notified_at')),
                    self::timestamp($row->stringOrNull('created_at')) ?? 0
                );
            } catch (InvalidValue) {
                continue;
            }
        }

        return $entries;
    }

    private static function timestamp(?string $utc): ?int
    {
        if (null === $utc) {
            return null;
        }
        $time = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $utc, new \DateTimeZone('UTC'));

        return false === $time ? null : $time->getTimestamp();
    }

    private static function utc(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
