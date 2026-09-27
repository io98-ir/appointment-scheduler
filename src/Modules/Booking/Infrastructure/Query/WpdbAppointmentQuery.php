<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Query;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\AppointmentDetail;
use Vaqtyar\Modules\Booking\Application\AppointmentFilter;
use Vaqtyar\Modules\Booking\Application\AppointmentQuery;
use Vaqtyar\Modules\Booking\Application\AppointmentRow;
use Vaqtyar\Modules\Booking\Application\AppointmentSearch;
use Vaqtyar\Modules\Booking\Application\AppointmentSort;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\PaymentStatus;
use Vaqtyar\Modules\Booking\Domain\Hold;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Shared\Domain\Money;

/**
 * AppointmentQuery on the appointments table and its children. Every
 * query has a range or an equality on an indexed column (implementation
 * notes §4.15, with the EXPLAIN checks in the integration suite).
 */
final class WpdbAppointmentQuery implements AppointmentQuery
{
    private const UTC_FORMAT = 'Y-m-d H:i:s';

    private const DAY = 86_400;

    private const COLUMNS = 'id, code, status, payment_status, customer_id, location_id, service_id, variant_id,
        staff_id, start_at, end_at, timezone, party_size, price_total';

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * @return list<AppointmentRow>
     */
    public function list(
        AppointmentFilter $filter,
        ?AppointmentSearch $search,
        AppointmentSort $sort,
        int $offset,
        int $limit,
    ): array {
        [$where, $args] = self::where($filter, $search);
        $rows = $this->db->getResults(
            'SELECT ' . self::COLUMNS . ' FROM %i WHERE ' . $where
                . ' ORDER BY ' . self::order($sort) . ' LIMIT %d OFFSET %d',
            Tables::name('appointments'),
            ...[...$args, $limit, $offset]
        );

        return \array_map(static fn (array $values): AppointmentRow => self::row(new Row($values)), $rows);
    }

    public function count(AppointmentFilter $filter, ?AppointmentSearch $search): int
    {
        [$where, $args] = self::where($filter, $search);

        return (int) $this->db->getVar(
            'SELECT COUNT(*) FROM %i WHERE ' . $where,
            Tables::name('appointments'),
            ...$args
        );
    }

    public function detail(int $id): ?AppointmentDetail
    {
        $rows = $this->db->getResults(
            'SELECT ' . self::COLUMNS . ', uuid, source, price_lines, customer_note, internal_note, created_by,
                created_at, cancelled_at, cancel_reason
            FROM %i WHERE id = %d',
            Tables::name('appointments'),
            $id
        );
        if ([] === $rows) {
            return null;
        }
        $row = new Row($rows[0]);
        $lines = \json_decode($row->string('price_lines'), true);
        $cancelledAt = $row->stringOrNull('cancelled_at');

        return new AppointmentDetail(
            self::row($row),
            $row->string('uuid'),
            $row->string('source'),
            PriceQuote::fromArray(['lines' => \is_array($lines) ? $lines : []]),
            $row->string('customer_note'),
            $row->string('internal_note'),
            $this->extras($id),
            $this->answers($id),
            $this->history($id),
            $row->intOrNull('created_by'),
            self::timestamp($row->string('created_at')),
            null === $cancelledAt ? null : self::timestamp($cancelledAt),
            $row->stringOrNull('cancel_reason')
        );
    }

    /**
     * No appointment is longer than an occupancy may be, so a start more
     * than that before the range cannot reach it: the lower bound keeps the
     * index range to the days asked for.
     *
     * @param list<int> $staffIds
     * @param list<AppointmentStatus> $statuses
     * @return list<AppointmentRow>
     */
    public function between(
        int $from,
        int $to,
        array $staffIds,
        ?int $locationId,
        array $statuses,
        int $limit,
    ): array {
        if ([] === $statuses) {
            return [];
        }
        $where = 'status IN (' . self::placeholders(\count($statuses), '%s') . ')
            AND start_at < %s AND start_at > %s AND end_at > %s';
        $args = [
            ...\array_map(static fn (AppointmentStatus $status): string => $status->value, $statuses),
            \gmdate(self::UTC_FORMAT, $to),
            \gmdate(self::UTC_FORMAT, $from - Hold::MAX_SPAN_SECONDS),
            \gmdate(self::UTC_FORMAT, $from),
        ];
        if ([] !== $staffIds) {
            $where .= ' AND staff_id IN (' . self::placeholders(\count($staffIds), '%d') . ')';
            $args = [...$args, ...$staffIds];
        }
        if (null !== $locationId) {
            $where .= ' AND location_id = %d';
            $args[] = $locationId;
        }
        $rows = $this->db->getResults(
            'SELECT ' . self::COLUMNS . ' FROM %i WHERE ' . $where . ' ORDER BY start_at, id LIMIT %d',
            Tables::name('appointments'),
            ...[...$args, $limit]
        );

        return \array_map(static fn (array $values): AppointmentRow => self::row(new Row($values)), $rows);
    }

    /**
     * The local dates are the location's, so each has a UTC range of its
     * own. A day before the first and after the last date covers every
     * offset (UTC−12 to UTC+14) and lets the start_at index bound the scan;
     * local_date then decides.
     *
     * @return array{literal-string, list<int|string>}
     */
    private static function where(AppointmentFilter $filter, ?AppointmentSearch $search): array
    {
        $where = '1 = 1';
        $args = [];
        if ([] !== $filter->statuses) {
            $where .= ' AND status IN (' . self::placeholders(\count($filter->statuses), '%s') . ')';
            $args = [
                ...$args,
                ...\array_map(static fn (AppointmentStatus $status): string => $status->value, $filter->statuses),
            ];
        }
        if ([] !== $filter->staffIds) {
            $where .= ' AND staff_id IN (' . self::placeholders(\count($filter->staffIds), '%d') . ')';
            $args = [...$args, ...$filter->staffIds];
        }
        foreach (
            [
                'service_id' => $filter->serviceId,
                'location_id' => $filter->locationId,
                'customer_id' => $filter->customerId,
            ] as $column => $id
        ) {
            if (null !== $id) {
                $where .= ' AND ' . $column . ' = %d';
                $args[] = $id;
            }
        }
        $utc = new \DateTimeZone('UTC');
        if (null !== $filter->from) {
            $where .= ' AND start_at >= %s AND local_date >= %s';
            $args[] = \gmdate(self::UTC_FORMAT, $filter->from->startOfDay($utc)->getTimestamp() - self::DAY);
            $args[] = $filter->from->toString();
        }
        if (null !== $filter->to) {
            $where .= ' AND start_at < %s AND local_date <= %s';
            $args[] = \gmdate(self::UTC_FORMAT, $filter->to->startOfDay($utc)->getTimestamp() + 2 * self::DAY);
            $args[] = $filter->to->toString();
        }
        if (null !== $search) {
            $matches = [];
            if (null !== $search->code) {
                $matches[] = 'code = %s';
                $args[] = $search->code;
            }
            if ([] !== $search->customerIds) {
                $matches[] = 'customer_id IN (' . self::placeholders(\count($search->customerIds), '%d') . ')';
                $args = [...$args, ...$search->customerIds];
            }
            $where .= [] === $matches ? ' AND 1 = 0' : ' AND (' . \implode(' OR ', $matches) . ')';
        }

        return [$where, $args];
    }

    /**
     * @return literal-string
     */
    private static function order(AppointmentSort $sort): string
    {
        return match ($sort) {
            AppointmentSort::StartAsc => 'start_at ASC, id ASC',
            AppointmentSort::StartDesc => 'start_at DESC, id DESC',
            AppointmentSort::CreatedAsc => 'id ASC',
            AppointmentSort::CreatedDesc => 'id DESC',
        };
    }

    /**
     * @param '%d'|'%s' $placeholder
     * @return literal-string
     */
    private static function placeholders(int $count, string $placeholder): string
    {
        return \implode(',', \array_fill(0, $count, $placeholder));
    }

    private static function row(Row $row): AppointmentRow
    {
        return new AppointmentRow(
            $row->int('id'),
            $row->string('code'),
            AppointmentStatus::from($row->string('status')),
            PaymentStatus::from($row->string('payment_status')),
            $row->int('customer_id'),
            $row->int('location_id'),
            $row->int('service_id'),
            $row->int('variant_id'),
            $row->int('staff_id'),
            self::timestamp($row->string('start_at')),
            self::timestamp($row->string('end_at')),
            $row->string('timezone'),
            $row->int('party_size'),
            Money::ofRial($row->int('price_total'))
        );
    }

    /**
     * @return list<array{extra_id: int, qty: int, unit_price: int}>
     */
    private function extras(int $id): array
    {
        $rows = $this->db->getResults(
            'SELECT extra_id, qty, price FROM %i WHERE appointment_id = %d ORDER BY id',
            Tables::name('appointment_extras'),
            $id
        );

        return \array_map(static function (array $values): array {
            $row = new Row($values);

            return ['extra_id' => $row->int('extra_id'), 'qty' => $row->int('qty'), 'unit_price' => $row->int('price')];
        }, $rows);
    }

    /**
     * @return array<string, string>
     */
    private function answers(int $id): array
    {
        $answers = [];
        $rows = $this->db->getResults(
            'SELECT field_key, value FROM %i WHERE appointment_id = %d ORDER BY id',
            Tables::name('appointment_answers'),
            $id
        );
        foreach ($rows as $values) {
            $row = new Row($values);
            $answers[$row->string('field_key')] = $row->string('value');
        }

        return $answers;
    }

    /**
     * @return list<array{
     *     action: string,
     *     from: ?AppointmentStatus,
     *     to: ?AppointmentStatus,
     *     changes: array<mixed>,
     *     actor_type: string,
     *     actor_id: ?int,
     *     reason: ?string,
     *     at: int
     * }>
     */
    private function history(int $id): array
    {
        $rows = $this->db->getResults(
            'SELECT action, from_status, to_status, changes, actor_type, actor_id, reason, created_at
            FROM %i WHERE appointment_id = %d ORDER BY id',
            Tables::name('appointment_history'),
            $id
        );

        return \array_map(static function (array $values): array {
            $row = new Row($values);
            $from = $row->stringOrNull('from_status');
            $to = $row->stringOrNull('to_status');
            $changes = \json_decode($row->stringOrNull('changes') ?? '[]', true);

            return [
                'action' => $row->string('action'),
                'from' => null === $from ? null : AppointmentStatus::tryFrom($from),
                'to' => null === $to ? null : AppointmentStatus::tryFrom($to),
                'changes' => \is_array($changes) ? $changes : [],
                'actor_type' => $row->string('actor_type'),
                'actor_id' => $row->intOrNull('actor_id'),
                'reason' => $row->stringOrNull('reason'),
                'at' => self::timestamp($row->string('created_at')),
            ];
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
