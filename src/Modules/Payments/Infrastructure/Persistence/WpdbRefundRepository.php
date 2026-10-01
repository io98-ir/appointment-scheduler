<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Payments\Application\RefundRepository;
use Vaqtyar\Modules\Payments\Application\RefundService;
use Vaqtyar\Shared\Domain\Money;

/**
 * RefundRepository on the refunds table.
 */
final class WpdbRefundRepository implements RefundRepository
{
    public function __construct(private readonly Db $db)
    {
    }

    public function add(int $paymentId, int $appointmentId, Money $amount, string $reason, ?int $userId, int $now): int
    {
        $at = \gmdate('Y-m-d H:i:s', $now);

        return $this->db->insert(Tables::name('refunds'), [
            'payment_id' => $paymentId,
            'appointment_id' => $appointmentId,
            'amount' => $amount->amount,
            'method' => RefundService::METHOD_MANUAL,
            'status' => RefundService::STATUS_RECORDED,
            'reason' => '' === $reason ? null : $reason,
            'created_by' => $userId,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function refundedTotal(int $paymentId): int
    {
        $total = $this->db->getVar(
            'SELECT COALESCE(SUM(amount), 0) FROM %i WHERE payment_id = %d',
            Tables::name('refunds'),
            $paymentId
        );

        return \is_numeric($total) ? (int) $total : 0;
    }

    public function refundedTotalOfAppointment(int $appointmentId): int
    {
        $total = $this->db->getVar(
            'SELECT COALESCE(SUM(amount), 0) FROM %i WHERE appointment_id = %d',
            Tables::name('refunds'),
            $appointmentId
        );

        return \is_numeric($total) ? (int) $total : 0;
    }
}
