<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\PricingReader;
use Vaqtyar\Modules\Booking\Domain\Pricing\Coupon;
use Vaqtyar\Modules\Booking\Domain\Pricing\CouponType;
use Vaqtyar\Modules\Booking\Domain\Pricing\TimeRule;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;

/**
 * PricingReader on price_rules and coupons. A time rule's config is
 * {"weekdays": [0-6], "from": "HH:MM", "to": "HH:MM", "valid_from":
 * "YYYY-MM-DD"|null, "valid_to": "YYYY-MM-DD"|null, "percent": int}.
 * The admin screen (T3.5) writes only what TimeRule accepts; a row that no
 * longer parses is skipped, so one bad row cannot stop every booking.
 */
final class WpdbPricingReader implements PricingReader
{
    private const TYPE_TIME = 'time';
    private const ACTIVE = 'active';

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * @return list<TimeRule>
     */
    public function timeRules(int $serviceId): array
    {
        $rows = $this->db->getResults(
            'SELECT id, config FROM %i WHERE type = %s AND status = %s AND (service_id IS NULL OR service_id = %d)
            ORDER BY priority DESC, id',
            Tables::name('price_rules'),
            self::TYPE_TIME,
            self::ACTIVE,
            $serviceId
        );
        $rules = [];
        foreach ($rows as $values) {
            $row = new Row($values);
            $rule = self::parse($row->int('id'), $row->string('config'));
            if (null !== $rule) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    public function coupon(string $code): ?Coupon
    {
        // The table's collation compares codes without regard to case.
        $rows = $this->db->getResults(
            'SELECT id, code, type, value, status, valid_from, valid_to, max_uses, used, service_ids FROM %i
            WHERE code = %s',
            Tables::name('coupons'),
            $code
        );
        if ([] === $rows) {
            return null;
        }
        $row = new Row($rows[0]);
        $type = CouponType::tryFrom($row->string('type'));
        $services = \json_decode($row->stringOrNull('service_ids') ?? 'null', true);
        if (null === $type) {
            return null;
        }

        return new Coupon(
            $row->int('id'),
            $row->string('code'),
            $type,
            $row->int('value'),
            self::ACTIVE === $row->string('status'),
            self::timestamp($row->stringOrNull('valid_from')),
            self::timestamp($row->stringOrNull('valid_to')),
            $row->intOrNull('max_uses'),
            $row->int('used'),
            \is_array($services) ? \array_values(\array_filter($services, 'is_int')) : null
        );
    }

    private static function timestamp(?string $utc): ?int
    {
        if (null === $utc) {
            return null;
        }
        $time = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $utc, new \DateTimeZone('UTC'));

        return false === $time ? null : $time->getTimestamp();
    }

    private static function parse(int $id, string $json): ?TimeRule
    {
        $config = \json_decode($json, true);
        if (!\is_array($config)) {
            return null;
        }
        $weekdays = $config['weekdays'] ?? [];
        $from = $config['from'] ?? null;
        $to = $config['to'] ?? null;
        $percent = $config['percent'] ?? null;
        if (!\is_array($weekdays) || !\is_string($from) || !\is_string($to) || !\is_int($percent)) {
            return null;
        }
        try {
            return new TimeRule(
                $id,
                \array_values(\array_filter($weekdays, 'is_int')),
                LocalTime::fromString($from)->minutes,
                LocalTime::fromString($to)->minutes,
                self::date($config['valid_from'] ?? null),
                self::date($config['valid_to'] ?? null),
                $percent
            );
        } catch (InvalidValue) {
            return null;
        }
    }

    private static function date(mixed $value): ?LocalDate
    {
        return \is_string($value) ? LocalDate::fromString($value) : null;
    }
}
