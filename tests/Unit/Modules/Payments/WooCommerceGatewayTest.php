<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Payments\Application\GatewayException;
use Vaqtyar\Modules\Payments\Application\WcOrderState;
use Vaqtyar\Modules\Payments\Infrastructure\WooCommerceGateway;
use Vaqtyar\Shared\Domain\Money;

final class WooCommerceGatewayTest extends TestCase
{
    public function testItHasAStableLowercaseIdAndReadsTheOrderFromTheCallback(): void
    {
        $gateway = new WooCommerceGateway(new FakeWcOrders('IRR'));

        self::assertSame('woocommerce', $gateway->id());
        self::assertSame('41', $gateway->callbackAuthority(['order' => '41', 'key' => 'wc_order_x']));
        self::assertNull($gateway->callbackAuthority(['key' => 'wc_order_x']));
    }

    public function testARialShopGetsTheAmountAsIs(): void
    {
        $orders = new FakeWcOrders('IRR');

        $started = (new WooCommerceGateway($orders))->start(7, Money::ofRial(1_250_000), 'https://x.test/cb');

        self::assertSame(1_250_000, $orders->created[0]['total']);
        self::assertSame('https://x.test/cb', $orders->created[0]['callback']);
        self::assertSame('https://shop.test/pay/' . $started->authority, $started->redirectUrl);
    }

    public function testATomanShopGetsTheAmountInTomans(): void
    {
        $orders = new FakeWcOrders('IRT');

        (new WooCommerceGateway($orders))->start(7, Money::ofRial(1_250_000), 'https://x.test/cb');

        self::assertSame(125_000, $orders->created[0]['total']);
    }

    public function testAFractionOfATomanIsRefused(): void
    {
        $this->expectException(GatewayException::class);

        (new WooCommerceGateway(new FakeWcOrders('IRT')))->start(7, Money::ofRial(1_250_005), 'https://x.test/cb');
    }

    public function testAnotherCurrencyIsRefusedSoTheNextGatewayIsTried(): void
    {
        $this->expectException(GatewayException::class);

        (new WooCommerceGateway(new FakeWcOrders('USD')))->start(7, Money::ofRial(1_000_000), 'https://x.test/cb');
    }

    public function testAPaidOrderIsPaidWithItsTransactionAsReference(): void
    {
        $orders = new FakeWcOrders('IRT');
        $orders->states['41'] = new WcOrderState(WcOrderState::PAID, 125_000, 'TX-9');

        $verdict = (new WooCommerceGateway($orders))->verify('41', Money::ofRial(1_250_000), []);

        self::assertTrue($verdict->paid);
        self::assertSame('TX-9', $verdict->refId);
    }

    public function testAPaidOrderWithoutATransactionIdIsReferencedByItself(): void
    {
        $orders = new FakeWcOrders('IRR');
        $orders->states['41'] = new WcOrderState(WcOrderState::PAID, 1000);

        self::assertSame('41', (new WooCommerceGateway($orders))->verify('41', Money::ofRial(1000), [])->refId);
    }

    public function testAnOrderThatCanStillBePaidIsNotAVerdict(): void
    {
        $orders = new FakeWcOrders('IRR');
        $orders->states['41'] = new WcOrderState(WcOrderState::AWAITING, 1000);

        $this->expectException(GatewayException::class);
        (new WooCommerceGateway($orders))->verify('41', Money::ofRial(1000), []);
    }

    public function testACancelledOrderAndAnUnknownOneAreNotPaid(): void
    {
        $orders = new FakeWcOrders('IRR');
        $orders->states['41'] = new WcOrderState(WcOrderState::CLOSED, 1000);
        $gateway = new WooCommerceGateway($orders);

        self::assertFalse($gateway->verify('41', Money::ofRial(1000), [])->paid);
        self::assertFalse($gateway->verify('99', Money::ofRial(1000), [])->paid);
    }

    public function testAPaidOrderWhoseTotalWasChangedIsLeftForAPerson(): void
    {
        $orders = new FakeWcOrders('IRR');
        $orders->states['41'] = new WcOrderState(WcOrderState::PAID, 900);

        $this->expectException(GatewayException::class);
        (new WooCommerceGateway($orders))->verify('41', Money::ofRial(1000), []);
    }
}
