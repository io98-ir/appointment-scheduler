<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Payments\Application\PaymentRepository;
use Vaqtyar\Modules\Payments\Application\PaymentService;
use Vaqtyar\Modules\Payments\Domain\Payment;
use Vaqtyar\Modules\Payments\Domain\PaymentStatus;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Money;

/**
 * PaymentRepository on the payments table.
 */
final class WpdbPaymentRepository implements PaymentRepository
{
    private const DUPLICATE_KEY = 1062;

    public function __construct(private readonly Db $db)
    {
    }

    public function add(Payment $payment, int $now): Payment
    {
        try {
            $id = $this->db->insert(Tables::name('payments'), [
                'appointment_id' => $payment->appointmentId,
                'gateway' => $payment->gateway,
                'amount' => $payment->amount->amount,
                'status' => $payment->status->value,
                'authority' => $payment->authority,
                'created_at' => self::utc($now),
                'updated_at' => self::utc($now),
            ]);
        } catch (DbException $e) {
            if (self::DUPLICATE_KEY === $e->errno) {
                throw new Conflict('authority_taken', 'The gateway already has this payment.');
            }
            throw $e;
        }

        return new Payment(
            $id,
            $payment->appointmentId,
            $payment->gateway,
            $payment->amount,
            $payment->status,
            $payment->authority
        );
    }

    public function find(string $gateway, string $authority, bool $forUpdate = false): ?Payment
    {
        $rows = $forUpdate
            ? $this->db->getResults(
                'SELECT * FROM %i WHERE gateway = %s AND authority = %s FOR UPDATE',
                Tables::name('payments'),
                $gateway,
                $authority
            )
            : $this->db->getResults(
                'SELECT * FROM %i WHERE gateway = %s AND authority = %s',
                Tables::name('payments'),
                $gateway,
                $authority
            );
        if ([] === $rows) {
            return null;
        }

        return self::hydrate(new Row($rows[0]));
    }

    public function findById(int $id, bool $forUpdate = false): ?Payment
    {
        $rows = $forUpdate
            ? $this->db->getResults('SELECT * FROM %i WHERE id = %d FOR UPDATE', Tables::name('payments'), $id)
            : $this->db->getResults('SELECT * FROM %i WHERE id = %d', Tables::name('payments'), $id);

        return [] === $rows ? null : self::hydrate(new Row($rows[0]));
    }

    private static function hydrate(Row $row): ?Payment
    {
        $status = PaymentStatus::tryFrom($row->string('status'));

        return null === $status ? null : new Payment(
            $row->int('id'),
            $row->int('appointment_id'),
            $row->string('gateway'),
            Money::ofRial($row->int('amount')),
            $status,
            $row->string('authority'),
            $row->stringOrNull('ref_id'),
            $row->stringOrNull('card_mask')
        );
    }

    /**
     * @return list<Payment>
     */
    public function awaitingBefore(int $cutoff, int $limit): array
    {
        $rows = $this->db->getResults(
            'SELECT * FROM %i WHERE status = %s AND gateway <> %s AND created_at < %s ORDER BY created_at ASC LIMIT %d',
            Tables::name('payments'),
            PaymentStatus::AwaitingCallback->value,
            PaymentService::OFFLINE,
            self::utc($cutoff),
            $limit
        );
        $payments = [];
        foreach ($rows as $row) {
            $payment = self::hydrate(new Row($row));
            if (null !== $payment) {
                $payments[] = $payment;
            }
        }

        return $payments;
    }

    public function settle(Payment $payment, int $now): bool
    {
        return 1 === $this->db->update(
            Tables::name('payments'),
            [
                'status' => $payment->status->value,
                'ref_id' => $payment->refId,
                'card_mask' => $payment->cardMask,
                'verified_at' => self::utc($now),
                'updated_at' => self::utc($now),
            ],
            ['id' => $payment->id ?? 0, 'status' => PaymentStatus::AwaitingCallback->value]
        );
    }

    private static function utc(int $timestamp): string
    {
        return \gmdate('Y-m-d H:i:s', $timestamp);
    }
}
