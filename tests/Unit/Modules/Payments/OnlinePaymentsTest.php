<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Payments\Application\GatewayRegistry;
use Vaqtyar\Modules\Payments\Application\OnlinePayments;
use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Modules\Payments\Application\PaymentLedger;
use Vaqtyar\Modules\Payments\Application\PaymentService;
use Vaqtyar\Modules\Payments\Application\StartedAttempt;
use Vaqtyar\Modules\Payments\Application\Verification;
use Vaqtyar\Modules\Payments\Domain\Payment;
use Vaqtyar\Modules\Payments\Domain\PaymentStatus;
use Vaqtyar\Modules\Payments\Infrastructure\OfflineGateway;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\TransactionRunner;

final class OnlinePaymentsTest extends TestCase
{
    /** @var list<string> the callback urls gateways were given */
    private array $callbacks = [];

    private MemoryPayments $payments;

    private MemoryRefunds $refunds;

    private bool $staff = true;

    /** @var list<Payment> */
    private array $succeeded = [];

    public function testOnlyTheOfflineGatewayIsNotOnline(): void
    {
        self::assertFalse($this->api([new OfflineGateway()])->onlineAvailable());
        self::assertTrue($this->api([new OfflineGateway(), $this->gateway('zibal')])->onlineAvailable());
    }

    public function testItSkipsTheOfflineGatewayAndGivesEachGatewayItsOwnCallback(): void
    {
        $api = $this->api([new OfflineGateway(), $this->gateway('zibal')]);

        $url = $api->startOnline(7, Money::ofRial(500_000), 'https://site.test/book');

        self::assertSame('https://pay.test/zibal', $url);
        self::assertSame(['https://site.test/pay/zibal?return=https%3A%2F%2Fsite.test%2Fbook'], $this->callbacks);
    }

    public function testWithNoOnlineGatewayItIsAConflict(): void
    {
        $this->expectException(Conflict::class);
        $this->api([new OfflineGateway()])->startOnline(7, Money::ofRial(500_000), 'https://site.test/book');
    }

    public function testTheTotalsAreWhatWentThroughMinusNothingAndWhatWasRefunded(): void
    {
        $api = $this->api([new OfflineGateway()]);
        $this->payments->add(new Payment(null, 7, 'zibal', Money::ofRial(900_000), PaymentStatus::Succeeded, 'A'), 1);
        $this->payments->add(new Payment(null, 7, 'zibal', Money::ofRial(400_000), PaymentStatus::Failed, 'B'), 1);
        $awaiting = PaymentStatus::AwaitingCallback;
        $this->payments->add(new Payment(null, 7, 'zibal', Money::ofRial(100_000), $awaiting, 'C'), 1);
        $this->payments->add(new Payment(null, 8, 'zibal', Money::ofRial(5), PaymentStatus::Succeeded, 'D'), 1);
        $this->refunds->add(1, 7, Money::ofRial(200_000), '', null, 1);

        $totals = $api->totals(7);

        self::assertSame([900_000, 200_000], [$totals->paid, $totals->refunded]);
        self::assertSame([0, 0], [$api->totals(99)->paid, $api->totals(99)->refunded]);
    }

    public function testStaffRecordMoneyReceivedAsAPaymentThatHasGoneThrough(): void
    {
        $service = $this->service([new OfflineGateway()]);

        $payment = $service->recordOffline(7, Money::ofRial(1_500_000));

        self::assertSame(['offline', PaymentStatus::Succeeded], [$payment->gateway, $payment->status]);
        self::assertSame(1_500_000, $this->payments->paidTotal(7));
        self::assertCount(1, $this->succeeded, 'The success event reaches Booking.');
        self::assertStringStartsWith('recorded-', $payment->authority);
    }

    public function testRecordingNeedsTheCapabilityAndAPositiveAmount(): void
    {
        $service = $this->service([new OfflineGateway()]);
        try {
            $service->recordOffline(7, Money::zero());
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('invalid_amount', $e->errorCode);
        }
        $this->staff = false;
        try {
            $service->recordOffline(7, Money::ofRial(1000));
            self::fail('No exception.');
        } catch (Forbidden) {
            self::addToAssertionCount(1);
        }
        self::assertSame(0, $this->payments->paidTotal(7));
        self::assertSame([], $this->succeeded);
    }

    public function testTheLedgerListsPaymentsWithTheirRefundsForStaffOnly(): void
    {
        $service = $this->service([new OfflineGateway()]);
        $ledger = $this->ledger();
        $service->recordOffline(7, Money::ofRial(1_000));
        $this->refunds->add(1, 7, Money::ofRial(300), '', null, 1);

        $entries = $ledger->entries(7);

        self::assertCount(1, $entries);
        self::assertSame([1_000, 300], [$entries[0]->payment->amount->amount, $entries[0]->refunded]);
        $this->staff = false;
        $this->expectException(Forbidden::class);
        $ledger->entries(7);
    }

    /**
     * @param list<PaymentGateway> $gateways
     */
    private function api(array $gateways): OnlinePayments
    {
        $this->callbacks = [];

        return new OnlinePayments(
            $this->service($gateways),
            new GatewayRegistry($gateways),
            $this->ledger(),
            static fn (string $returnUrl): string => 'https://site.test/pay/{gateway}?return='
                . \rawurlencode($returnUrl)
        );
    }

    /**
     * @param list<PaymentGateway> $gateways
     */
    private function service(array $gateways): PaymentService
    {
        $this->payments = new MemoryPayments();
        $this->refunds = new MemoryRefunds();
        $this->succeeded = [];

        return new PaymentService(
            new GatewayRegistry($gateways),
            $this->payments,
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
            $this->authorizer(),
            function (Payment $payment): void {
                $this->succeeded[] = $payment;
            }
        );
    }

    private function ledger(): PaymentLedger
    {
        return new PaymentLedger($this->payments, $this->refunds, $this->authorizer());
    }

    private function authorizer(): Authorizer
    {
        return new class ($this) implements Authorizer {
            public function __construct(private readonly OnlinePaymentsTest $test)
            {
            }

            public function allows(string $capability): bool
            {
                return $this->test->staff();
            }
        };
    }

    public function staff(): bool
    {
        return $this->staff;
    }

    private function gateway(string $id): PaymentGateway
    {
        return new class ($id, $this) implements PaymentGateway {
            public function __construct(private readonly string $id, private readonly OnlinePaymentsTest $test)
            {
            }

            public function id(): string
            {
                return $this->id;
            }

            public function start(int $appointmentId, Money $amount, string $callbackUrl): StartedAttempt
            {
                $this->test->callbackSeen($callbackUrl);

                return new StartedAttempt('AUTH-1', 'https://pay.test/' . $this->id);
            }

            /**
             * @param array<string, string> $callbackParams
             */
            public function callbackAuthority(array $callbackParams): ?string
            {
                return null;
            }

            /**
             * @param array<string, string> $callbackParams
             */
            public function verify(string $authority, Money $amount, array $callbackParams): Verification
            {
                return new Verification(false);
            }
        };
    }

    public function callbackSeen(string $url): void
    {
        $this->callbacks[] = $url;
    }
}
