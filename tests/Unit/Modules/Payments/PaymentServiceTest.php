<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Payments\Application\GatewayException;
use Vaqtyar\Modules\Payments\Application\GatewayRegistry;
use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Modules\Payments\Application\PaymentRepository;
use Vaqtyar\Modules\Payments\Application\PaymentService;
use Vaqtyar\Modules\Payments\Application\StartedAttempt;
use Vaqtyar\Modules\Payments\Application\Verification;
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

final class PaymentServiceTest extends TestCase
{
    /** @var array<string, Payment> by "gateway|authority" */
    private array $stored = [];

    /** @var list<Payment> */
    private array $succeeded = [];

    private bool $allowed = true;

    private int $verifyCalls = 0;

    private bool $reachable = true;

    private bool $paid = true;

    public function testItStartsOnTheFirstGatewayThatAnswersAndFailsOverPastOneThatDoesNot(): void
    {
        $service = $this->service([$this->gateway('down', false), $this->gateway('up')]);

        $started = $service->start(7, Money::ofRial(1_000_000), 'https://x.test/cb');

        self::assertSame(['up', PaymentStatus::AwaitingCallback], [
            $started->payment->gateway,
            $started->payment->status,
        ]);
        self::assertSame('https://pay.test/up', $started->redirectUrl);
        self::assertCount(1, $this->stored);
    }

    public function testAPreferredGatewayGoesFirst(): void
    {
        $service = $this->service([$this->gateway('a'), $this->gateway('b')]);

        $started = $service->start(7, Money::ofRial(1000), 'https://x.test/cb', ['b']);

        self::assertSame('b', $started->payment->gateway);
    }

    public function testItRefusesWhenEveryGatewayIsDownAndForANonPositiveAmount(): void
    {
        $service = $this->service([$this->gateway('down', false)]);

        try {
            $service->start(7, Money::ofRial(1000), 'https://x.test/cb');
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('no_gateway_available', $e->errorCode);
        }
        $this->expectException(InvalidValue::class);
        $service->start(7, Money::zero(), 'https://x.test/cb');
    }

    public function testACallbackSettlesOnceAndARepeatChangesNothing(): void
    {
        $service = $this->service([$this->gateway('up')]);
        $authority = $service->start(7, Money::ofRial(1000), 'https://x.test/cb')->payment->authority;

        $first = $service->settle('up', $authority, []);
        $again = $service->settle('up', $authority, []);

        self::assertSame([PaymentStatus::Succeeded, PaymentStatus::Succeeded], [$first->status, $again->status]);
        self::assertSame('REF-1', $again->refId);
        self::assertCount(1, $this->succeeded, 'The success event fires once.');
        self::assertSame(1, $this->verifyCalls, 'A settled payment is not verified again.');
    }

    public function testAFailedVerdictSettlesAsFailedWithoutAnEvent(): void
    {
        $service = $this->service([$this->gateway('up')]);
        $authority = $service->start(7, Money::ofRial(1000), 'https://x.test/cb')->payment->authority;
        $this->paid = false;

        $settled = $service->settle('up', $authority, []);

        self::assertSame(PaymentStatus::Failed, $settled->status);
        self::assertSame([], $this->succeeded);
    }

    public function testAnUnreachableGatewayLeavesThePaymentAwaiting(): void
    {
        $service = $this->service([$this->gateway('up')]);
        $authority = $service->start(7, Money::ofRial(1000), 'https://x.test/cb')->payment->authority;
        $this->reachable = false;

        $left = $service->settle('up', $authority, []);

        self::assertSame(PaymentStatus::AwaitingCallback, $left->status);
        $this->reachable = true;
        self::assertSame(PaymentStatus::Succeeded, $service->settle('up', $authority, [])->status);
    }

    public function testAnUnknownGatewayOrAuthorityIsNotFound(): void
    {
        $service = $this->service([$this->gateway('up')]);

        foreach ([['nope', 'x'], ['up', 'missing']] as [$gateway, $authority]) {
            try {
                $service->settle($gateway, $authority, []);
                self::fail('No exception.');
            } catch (NotFound $e) {
                self::assertSame('payment_not_found', $e->errorCode);
            }
        }
    }

    public function testACallbackIsMatchedByTheGatewaysOwnParameter(): void
    {
        $service = $this->service([$this->gateway('up')]);
        $authority = $service->start(7, Money::ofRial(1000), 'https://x.test/cb')->payment->authority;

        self::assertSame(
            PaymentStatus::Succeeded,
            $service->settleCallback('up', ['authority' => $authority])->status
        );
        foreach ([['up', []], ['nope', ['authority' => $authority]]] as [$gateway, $params]) {
            try {
                $service->settleCallback($gateway, $params);
                self::fail('No exception.');
            } catch (NotFound $e) {
                self::assertSame('payment_not_found', $e->errorCode);
            }
        }
    }

    public function testReconcilingSettlesWhatTheCustomerNeverReturnedFromAndSkipsOffline(): void
    {
        $service = $this->service([$this->gateway('up'), $this->gateway('offline')]);
        $service->start(7, Money::ofRial(1000), 'https://x.test/cb', ['up']);
        $service->start(8, Money::ofRial(1000), 'https://x.test/cb', ['offline']);

        self::assertSame(1, $service->reconcile(900, 50));
        self::assertCount(1, $this->succeeded);
        self::assertSame(0, $service->reconcile(900, 50), 'Nothing is left to settle.');

        $this->reachable = false;
        $service->start(9, Money::ofRial(1000), 'https://x.test/cb', ['up']);
        self::assertSame(0, $service->reconcile(900, 50), 'An unreachable gateway settles nothing.');
    }

    public function testOnlyStaffConfirmAnOfflinePayment(): void
    {
        $service = $this->service([$this->gateway('offline')]);
        $authority = $service->start(7, Money::ofRial(1000), 'https://x.test/cb')->payment->authority;
        $this->allowed = false;
        try {
            $service->confirmOffline($authority);
            self::fail('No exception.');
        } catch (Forbidden) {
        }
        $this->allowed = true;

        $confirmed = $service->confirmOffline($authority);

        self::assertSame(PaymentStatus::Succeeded, $confirmed->status);
        self::assertCount(1, $this->succeeded);
        self::assertSame(PaymentStatus::Succeeded, $service->confirmOffline($authority)->status);
        self::assertCount(1, $this->succeeded);
    }

    /**
     * @param list<PaymentGateway> $gateways
     */
    private function service(array $gateways): PaymentService
    {
        $this->stored = [];
        $this->succeeded = [];

        return new PaymentService(
            new GatewayRegistry($gateways),
            $this->repository(),
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
                public function __construct(private readonly PaymentServiceTest $test)
                {
                }

                public function allows(string $capability): bool
                {
                    return $this->test->allowed();
                }
            },
            function (Payment $payment): void {
                $this->succeeded[] = $payment;
            }
        );
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function verified(): ?Verification
    {
        ++$this->verifyCalls;

        return $this->reachable ? new Verification($this->paid, $this->paid ? 'REF-1' : null, null) : null;
    }

    private function gateway(string $id, bool $up = true): PaymentGateway
    {
        return new class ($id, $up, $this) implements PaymentGateway {
            private int $serial = 0;

            public function __construct(
                private readonly string $id,
                private readonly bool $up,
                private readonly PaymentServiceTest $test,
            ) {
            }

            public function id(): string
            {
                return $this->id;
            }

            public function start(int $appointmentId, Money $amount, string $callbackUrl): StartedAttempt
            {
                if (!$this->up) {
                    throw new GatewayException('down');
                }

                return new StartedAttempt($this->id . '-' . ++$this->serial, 'https://pay.test/' . $this->id);
            }

            /**
             * @param array<string, string> $callbackParams
             */
            public function callbackAuthority(array $callbackParams): ?string
            {
                return $callbackParams['authority'] ?? null;
            }

            /**
             * @param array<string, string> $callbackParams
             */
            public function verify(string $authority, Money $amount, array $callbackParams): Verification
            {
                return $this->test->verified() ?? throw new GatewayException('unreachable');
            }
        };
    }

    private function repository(): PaymentRepository
    {
        return new class ($this->stored) implements PaymentRepository {
            private int $next = 1;

            /** @param array<string, Payment> $stored */
            public function __construct(private array &$stored)
            {
            }

            public function add(Payment $payment, int $now): Payment
            {
                $added = new Payment(
                    $this->next++,
                    $payment->appointmentId,
                    $payment->gateway,
                    $payment->amount,
                    $payment->status,
                    $payment->authority
                );
                $this->stored[$payment->gateway . '|' . $payment->authority] = $added;

                return $added;
            }

            public function find(string $gateway, string $authority, bool $forUpdate = false): ?Payment
            {
                return $this->stored[$gateway . '|' . $authority] ?? null;
            }

            public function findById(int $id, bool $forUpdate = false): ?Payment
            {
                foreach ($this->stored as $payment) {
                    if ($payment->id === $id) {
                        return $payment;
                    }
                }

                return null;
            }

            /**
             * @return list<Payment>
             */
            public function awaitingBefore(int $cutoff, int $limit): array
            {
                return \array_values(\array_filter(
                    $this->stored,
                    static fn (Payment $p): bool => PaymentStatus::AwaitingCallback === $p->status
                        && 'offline' !== $p->gateway
                ));
            }

            public function settle(Payment $payment, int $now): bool
            {
                $key = $payment->gateway . '|' . $payment->authority;
                if (PaymentStatus::AwaitingCallback !== ($this->stored[$key]->status ?? null)) {
                    return false;
                }
                $this->stored[$key] = $payment;

                return true;
            }
        };
    }
}
