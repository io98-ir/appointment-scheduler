<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Payments\Application\PaymentRepository;
use Vaqtyar\Modules\Payments\Application\RefundRepository;
use Vaqtyar\Modules\Payments\Application\RefundService;
use Vaqtyar\Modules\Payments\Domain\Payment;
use Vaqtyar\Modules\Payments\Domain\PaymentStatus;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\TransactionRunner;

final class RefundServiceTest extends TestCase
{
    private bool $allowed = true;

    private PaymentStatus $status = PaymentStatus::Succeeded;

    /** @var list<array{int, int}> appointment and refund ids */
    private array $told = [];

    /** @var int rials refunded so far */
    private int $refunded = 0;

    public function testStaffRecordARefundAndTheEventFiresWithTheAppointment(): void
    {
        $id = $this->service()->record(5, Money::ofRial(300_000), 'customer asked', 9);

        self::assertSame(1, $id);
        self::assertSame(300_000, $this->refunded);
        self::assertSame([[7, 1]], $this->told);
    }

    public function testRefundsCannotTogetherExceedThePayment(): void
    {
        $service = $this->service();
        $service->record(5, Money::ofRial(600_000), '', 9);

        try {
            $service->record(5, Money::ofRial(500_000), '', 9);
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('refund_exceeds_payment', $e->errorCode);
        }
        self::assertSame(1, \count($this->told));
        self::assertSame(1, $service->record(5, Money::ofRial(400_000), '', 9) - 1, 'The exact remainder is allowed.');
    }

    public function testOnlyAPaymentThatWentThroughCanBeRefunded(): void
    {
        $this->status = PaymentStatus::AwaitingCallback;

        $this->expectException(Conflict::class);
        $this->service()->record(5, Money::ofRial(1000), '', 9);
    }

    public function testItNeedsTheCapabilityAPositiveAmountAndARealPayment(): void
    {
        $this->allowed = false;
        try {
            $this->service()->record(5, Money::ofRial(1000), '', 9);
            self::fail('No exception.');
        } catch (Forbidden) {
        }
        $this->allowed = true;
        try {
            $this->service()->record(5, Money::zero(), '', 9);
            self::fail('No exception.');
        } catch (InvalidValue) {
        }

        $this->expectException(NotFound::class);
        $this->service()->record(99, Money::ofRial(1000), '', 9);
    }

    private function service(): RefundService
    {
        $payment = new Payment(5, 7, 'zarinpal', Money::ofRial(1_000_000), $this->status, 'A1', '201', null);

        return new RefundService(
            new class ($payment) implements PaymentRepository {
                public function __construct(private readonly Payment $payment)
                {
                }

                public function add(Payment $payment, int $now): Payment
                {
                    return $payment;
                }

                public function find(string $gateway, string $authority, bool $forUpdate = false): ?Payment
                {
                    return null;
                }

                public function findById(int $id, bool $forUpdate = false): ?Payment
                {
                    return 5 === $id ? $this->payment : null;
                }

                /**
                 * @return list<Payment>
                 */
                public function awaitingBefore(int $cutoff, int $limit): array
                {
                    return [];
                }

                public function settle(Payment $payment, int $now): bool
                {
                    return false;
                }
            },
            new class ($this) implements RefundRepository {
                private int $next = 1;

                public function __construct(private readonly RefundServiceTest $test)
                {
                }

                public function add(
                    int $paymentId,
                    int $appointmentId,
                    Money $amount,
                    string $reason,
                    ?int $userId,
                    int $now
                ): int {
                    $this->test->refund($amount->amount);

                    return $this->next++;
                }

                public function refundedTotal(int $paymentId): int
                {
                    return $this->test->refunded();
                }
            },
            new class implements TransactionRunner {
                public function run(callable $work): mixed
                {
                    return $work();
                }
            },
            new class implements Clock {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('@1800000000');
                }
            },
            new class ($this) implements Authorizer {
                public function __construct(private readonly RefundServiceTest $test)
                {
                }

                public function allows(string $capability): bool
                {
                    return $this->test->allowed();
                }
            },
            function (int $appointmentId, int $refundId): void {
                $this->told[] = [$appointmentId, $refundId];
            }
        );
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function refund(int $amount): void
    {
        $this->refunded += $amount;
    }

    public function refunded(): int
    {
        return $this->refunded;
    }
}
