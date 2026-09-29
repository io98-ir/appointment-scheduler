<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Shared\Domain\Money;

/**
 * What every PaymentGateway must satisfy (T5.1); each adapter's test extends
 * it and says how to build the gateway and what a paying callback looks like.
 */
abstract class GatewayContractTest extends TestCase
{
    abstract protected function gateway(): PaymentGateway;

    /**
     * The callback parameters of a customer who did not pay.
     *
     * @return array<string, string>
     */
    abstract protected function unpaidParams(): array;

    public function testItHasAStableLowercaseId(): void
    {
        self::assertMatchesRegularExpression('/^[a-z][a-z0-9_]{0,31}$/', $this->gateway()->id());
    }

    public function testEachStartGetsItsOwnAuthority(): void
    {
        $gateway = $this->gateway();

        $first = $gateway->start(1, Money::ofRial(500_000), 'https://example.test/cb');
        $second = $gateway->start(1, Money::ofRial(500_000), 'https://example.test/cb');

        self::assertNotSame('', $first->authority);
        self::assertNotSame($first->authority, $second->authority);
    }

    public function testACallbackThatDidNotPayIsNotPaid(): void
    {
        $gateway = $this->gateway();
        $started = $gateway->start(1, Money::ofRial(500_000), 'https://example.test/cb');

        self::assertFalse(
            $gateway->verify($started->authority, Money::ofRial(500_000), $this->unpaidParams())->paid
        );
    }

    public function testTheSameQuestionGetsTheSameAnswer(): void
    {
        $gateway = $this->gateway();
        $started = $gateway->start(1, Money::ofRial(500_000), 'https://example.test/cb');
        $amount = Money::ofRial(500_000);

        $first = $gateway->verify($started->authority, $amount, $this->unpaidParams());
        $second = $gateway->verify($started->authority, $amount, $this->unpaidParams());

        self::assertEquals($first, $second);
    }
}
