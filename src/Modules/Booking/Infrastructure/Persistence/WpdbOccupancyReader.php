<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Domain\Hold;
use Vaqtyar\Modules\Booking\Domain\LockKey;
use Vaqtyar\Modules\Scheduling\Contracts\BusySpan;
use Vaqtyar\Modules\Scheduling\Contracts\OccupancyReader;
use Vaqtyar\Shared\Domain\Clock;

/**
 * OccupancyReader on the occupancies table: one query on the
 * (lock_key, start_at, end_at) index. Lock keys are "staff:{id}" and
 * "res:{id}" (data-model §2), the keys the locker takes (T2.2).
 */
final class WpdbOccupancyReader implements OccupancyReader
{
    private const UTC_FORMAT = 'Y-m-d H:i:s';

    /**
     * The longest occupancy the booking code may write (T2.2 refuses a
     * longer one). It bounds start_at from below, so the index range of each
     * key is [from - MAX_SPAN, to) and not the key's whole history.
     */
    public const MAX_SPAN_SECONDS = Hold::MAX_SPAN_SECONDS;

    public function __construct(private readonly Db $db, private readonly Clock $clock)
    {
    }

    /**
     * @param list<int> $staffIds
     * @param list<int> $resourceIds
     * @return list<BusySpan>
     */
    public function overlapping(array $staffIds, array $resourceIds, int $from, int $to): array
    {
        $keys = LockKey::sorted($staffIds, $resourceIds);
        if ([] === $keys || $from >= $to) {
            return [];
        }
        $placeholders = \implode(',', \array_fill(0, \count($keys), '%s'));
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format(self::UTC_FORMAT);

        $rows = $this->db->getResults(
            'SELECT lock_key, start_at, end_at, seats, variant_id, staff_id FROM %i
            WHERE lock_key IN (' . $placeholders . ') AND start_at < %s AND start_at > %s AND end_at > %s
            AND (expires_at IS NULL OR expires_at > %s)',
            Tables::name('occupancies'),
            ...$keys,
            ...[
                \gmdate(self::UTC_FORMAT, $to),
                \gmdate(self::UTC_FORMAT, $from - self::MAX_SPAN_SECONDS),
                \gmdate(self::UTC_FORMAT, $from),
                $now,
            ]
        );

        return \array_map(static function (array $values): BusySpan {
            $row = new Row($values);
            [$type, $id] = \explode(':', $row->string('lock_key'), 2);

            return new BusySpan(
                'res' === $type,
                (int) $id,
                self::timestamp($row->string('start_at')),
                self::timestamp($row->string('end_at')),
                $row->int('seats'),
                $row->intOrNull('variant_id'),
                $row->intOrNull('staff_id')
            );
        }, $rows);
    }

    private static function timestamp(string $utc): int
    {
        $time = \DateTimeImmutable::createFromFormat('!' . self::UTC_FORMAT, $utc, new \DateTimeZone('UTC'));

        if (false === $time) {
            throw new \UnexpectedValueException('A DATETIME column is not a date.');
        }

        return $time->getTimestamp();
    }
}
