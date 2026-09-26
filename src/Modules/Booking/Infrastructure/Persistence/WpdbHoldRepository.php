<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\HoldRepository;
use Vaqtyar\Modules\Booking\Application\StoredHold;
use Vaqtyar\Modules\Booking\Domain\Hold;

/**
 * HoldRepository on the holds and occupancies tables (data-model §2). An
 * occupancy of a hold has owner_type "hold" and the hold's expiry, so the
 * occupancy reader skips it once expired without a join.
 */
final class WpdbHoldRepository implements HoldRepository
{
    private const UTC_FORMAT = 'Y-m-d H:i:s';

    private const OWNER_TYPE = 'hold';

    public function __construct(private readonly Db $db)
    {
    }

    public function add(Hold $hold, string $tokenHash): int
    {
        $created = self::utc($hold->createdAt);
        $expires = self::utc($hold->expiresAt);
        $id = $this->db->insert(Tables::name('holds'), [
            'token_hash' => $tokenHash,
            'location_id' => $hold->locationId,
            'variant_id' => $hold->variantId,
            'staff_id' => $hold->staffId,
            'start_at' => self::utc($hold->start),
            'end_at' => self::utc($hold->end),
            'party_size' => $hold->partySize,
            'extras' => self::json($hold->extraIds),
            // The price comes with PriceCalculator (T2.3).
            'price_quote' => '{}',
            'expires_at' => $expires,
            'created_at' => $created,
            'updated_at' => $created,
        ]);
        foreach ($hold->lockKeys() as $key) {
            $this->db->insert(Tables::name('occupancies'), [
                'owner_type' => self::OWNER_TYPE,
                'owner_id' => $id,
                'lock_key' => $key,
                'variant_id' => $hold->variantId,
                'staff_id' => $hold->staffId,
                'start_at' => self::utc($hold->from),
                'end_at' => self::utc($hold->to),
                'seats' => $hold->partySize,
                'expires_at' => $expires,
            ]);
        }

        return $id;
    }

    public function find(string $tokenHash, bool $forUpdate = false): ?StoredHold
    {
        $sql = $forUpdate
            ? 'SELECT id, created_at, expires_at FROM %i WHERE token_hash = %s FOR UPDATE'
            : 'SELECT id, created_at, expires_at FROM %i WHERE token_hash = %s';
        $rows = $this->db->getResults($sql, Tables::name('holds'), $tokenHash);
        if ([] === $rows) {
            return null;
        }
        $hold = new Row($rows[0]);
        $id = $hold->int('id');
        $occupancies = $this->db->getResults(
            'SELECT lock_key, start_at, end_at FROM %i WHERE owner_type = %s AND owner_id = %d',
            Tables::name('occupancies'),
            self::OWNER_TYPE,
            $id
        );
        $keys = [];
        $from = \PHP_INT_MAX;
        $to = 0;
        foreach ($occupancies as $values) {
            $row = new Row($values);
            $keys[] = $row->string('lock_key');
            $from = \min($from, self::timestamp($row->string('start_at')));
            $to = \max($to, self::timestamp($row->string('end_at')));
        }
        \sort($keys, \SORT_STRING);

        return new StoredHold(
            $id,
            $keys,
            [] === $keys ? 0 : $from,
            $to,
            self::timestamp($hold->string('created_at')),
            self::timestamp($hold->string('expires_at'))
        );
    }

    public function extend(int $id, int $expiresAt): void
    {
        $expires = self::utc($expiresAt);
        $this->db->update(Tables::name('holds'), ['expires_at' => $expires, 'updated_at' => $expires], ['id' => $id]);
        $this->db->update(
            Tables::name('occupancies'),
            ['expires_at' => $expires],
            ['owner_type' => self::OWNER_TYPE, 'owner_id' => $id]
        );
    }

    public function purgeExpired(int $now, int $limit): int
    {
        $ids = \array_map(
            static fn (array $values): int => (new Row($values))->int('id'),
            $this->db->getResults(
                'SELECT id FROM %i WHERE expires_at <= %s ORDER BY expires_at LIMIT %d',
                Tables::name('holds'),
                self::utc($now),
                $limit
            )
        );
        if ([] !== $ids) {
            $placeholders = \implode(',', \array_fill(0, \count($ids), '%d'));
            $this->db->execute(
                'DELETE FROM %i WHERE owner_type = %s AND owner_id IN (' . $placeholders . ')',
                Tables::name('occupancies'),
                ...[self::OWNER_TYPE, ...$ids]
            );
            $this->db->execute(
                'DELETE FROM %i WHERE id IN (' . $placeholders . ')',
                Tables::name('holds'),
                ...$ids
            );
        }
        // No booking reaches back two days, so these rows lock nothing any more.
        $this->db->execute(
            'DELETE FROM %i WHERE day < %s LIMIT %d',
            Tables::name('resource_day_locks'),
            \gmdate('Y-m-d', $now - 2 * 86_400),
            $limit
        );

        return \count($ids);
    }

    private static function utc(int $timestamp): string
    {
        return \gmdate(self::UTC_FORMAT, $timestamp);
    }

    private static function timestamp(string $utc): int
    {
        $time = \DateTimeImmutable::createFromFormat('!' . self::UTC_FORMAT, $utc, new \DateTimeZone('UTC'));
        if (false === $time) {
            throw new \UnexpectedValueException('A DATETIME column is not a date.');
        }

        return $time->getTimestamp();
    }

    /**
     * @param list<int> $ids
     */
    private static function json(array $ids): string
    {
        return (string) \json_encode($ids, \JSON_THROW_ON_ERROR);
    }
}
