<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Domain\Pricing\Coupon;
use Vaqtyar\Modules\Booking\Domain\Pricing\CouponRepository;
use Vaqtyar\Modules\Booking\Domain\Pricing\CouponType;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * CouponRepository for the admin screen (T3.5), separate from
 * WpdbPricingReader (the booking-time read path, which locks the row and
 * counts uses): this one lists, writes and deletes. A row that no longer
 * parses into a Coupon is skipped from a listing, the same tolerance the
 * reader has.
 */
final class WpdbCouponRepository implements CouponRepository
{
    private const DUPLICATE_KEY = 1062;
    private const ACTIVE = 'active';
    private const INACTIVE = 'inactive';

    public function __construct(private readonly Db $db, private readonly Clock $clock)
    {
    }

    /**
     * @return list<Coupon>
     */
    public function all(): array
    {
        $rows = $this->db->getResults('SELECT * FROM %i ORDER BY id DESC', Tables::name('coupons'));
        $coupons = [];
        foreach ($rows as $values) {
            $coupon = self::coupon(new Row($values));
            if (null !== $coupon) {
                $coupons[] = $coupon;
            }
        }

        return $coupons;
    }

    public function find(int $id): ?Coupon
    {
        $rows = $this->db->getResults('SELECT * FROM %i WHERE id = %d', Tables::name('coupons'), $id);

        return [] === $rows ? null : self::coupon(new Row($rows[0]));
    }

    public function findByCode(string $code): ?Coupon
    {
        // The table's collation compares codes without regard to case.
        $rows = $this->db->getResults('SELECT * FROM %i WHERE code = %s', Tables::name('coupons'), $code);

        return [] === $rows ? null : self::coupon(new Row($rows[0]));
    }

    public function save(Coupon $coupon): Coupon
    {
        $now = $this->now();
        $values = [
            'code' => $coupon->code,
            'type' => $coupon->type->value,
            'value' => $coupon->value,
            'max_uses' => $coupon->maxUses,
            'valid_from' => null === $coupon->validFrom ? null : self::utc($coupon->validFrom),
            'valid_to' => null === $coupon->validTo ? null : self::utc($coupon->validTo),
            'service_ids' => null === $coupon->serviceIds ? null : (string) \wp_json_encode($coupon->serviceIds),
            'status' => $coupon->active ? self::ACTIVE : self::INACTIVE,
            'updated_at' => $now,
        ];
        $table = Tables::name('coupons');
        try {
            if (0 === $coupon->id) {
                $id = $this->db->insert($table, $values + ['created_at' => $now]);
            } else {
                $id = $coupon->id;
                $this->db->update($table, $values, ['id' => $id]);
            }
        } catch (DbException $e) {
            if (self::DUPLICATE_KEY === $e->errno) {
                throw new Conflict('coupon_code_taken', 'Another coupon has this code.');
            }
            throw $e;
        }

        return new Coupon(
            $id,
            $coupon->code,
            $coupon->type,
            $coupon->value,
            $coupon->active,
            $coupon->validFrom,
            $coupon->validTo,
            $coupon->maxUses,
            $this->used($id),
            $coupon->serviceIds
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM %i WHERE id = %d', Tables::name('coupons'), $id);
    }

    private function used(int $id): int
    {
        $rows = $this->db->getResults('SELECT used FROM %i WHERE id = %d', Tables::name('coupons'), $id);

        return [] === $rows ? 0 : (new Row($rows[0]))->int('used');
    }

    private static function coupon(Row $row): ?Coupon
    {
        $type = CouponType::tryFrom($row->string('type'));
        if (null === $type) {
            return null;
        }
        $services = \json_decode($row->stringOrNull('service_ids') ?? 'null', true);
        try {
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
        } catch (InvalidValue) {
            return null;
        }
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

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
