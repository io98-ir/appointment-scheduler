<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Payments\Application\GatewayRegistry;
use Vaqtyar\Modules\Payments\Application\OnlinePayments;
use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Modules\Payments\Application\PaymentRepository;
use Vaqtyar\Modules\Payments\Application\PaymentService;
use Vaqtyar\Modules\Payments\Application\StartedAttempt;
use Vaqtyar\Modules\Payments\Application\Verification;
use Vaqtyar\Modules\Payments\Domain\Payment;
use Vaqtyar\Modules\Payments\Infrastructure\OfflineGateway;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\TransactionRunner;

final class OnlinePaymentsTest extends TestCase
{
    /** @var list<string> the callback urls gateways were given */
    private array $callbacks = [];

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

    /**
     * @param list<PaymentGateway> $gateways
     */
    private function api(array $gateways): OnlinePayments
    {
        $this->callbacks = [];
        $registry = new GatewayRegistry($gateways);

        return new OnlinePayments(
            new PaymentService(
                $registry,
                new class () implements PaymentRepository {
                    public function add(Payment $payment, int $now): Payment
                    {
                        return new Payment(
                            1,
                            $payment->appointmentId,
                            $payment->gateway,
                            $payment->amount,
                            $payment->status,
                            $payment->authority
                        );
                    }

                    public function find(string $gateway, string $authority, bool $forUpdate = false): ?Payment
                    {
                        return null;
                    }

                    public function findById(int $id, bool $forUpdate = false): ?Payment
                    {
                        return null;
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
                new class implements Authorizer {
                    public function allows(string $capability): bool
                    {
                        return true;
                    }
                },
                static function (Payment $payment): void {
                }
            ),
            $registry,
            static fn (string $returnUrl): string => 'https://site.test/pay/{gateway}?return='
                . \rawurlencode($returnUrl)
        );
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
