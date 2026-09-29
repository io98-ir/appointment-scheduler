<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Payments;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Payments\Application\GatewayRegistry;
use Vaqtyar\Modules\Payments\Application\PaymentService;
use Vaqtyar\Modules\Payments\Domain\Payment;
use Vaqtyar\Modules\Payments\Domain\PaymentStatus;
use Vaqtyar\Modules\Payments\Infrastructure\Persistence\WpdbPaymentRepository;
use Vaqtyar\Modules\Payments\Infrastructure\WcOrderStore;
use Vaqtyar\Modules\Payments\Infrastructure\WooCommerceGateway;
use Vaqtyar\Modules\Payments\Infrastructure\WooCommerceHooks;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\SystemClock;
use Vaqtyar\Shared\WpAuthorizer;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * WooCommerce as a gateway on a real WooCommerce (T5.3), on its HPOS storage
 * when the bootstrap turned it on. Skipped where WooCommerce is not installed:
 * CI adds it to the integration job only, as it is an optional plugin.
 */
final class WooCommerceGatewayTest extends TestCase
{
    use RealDatabase;

    private const CALLBACK = 'https://example.test/wp-json/x/payments/callback/woocommerce?return=%2Fbook';

    /** @var list<Payment> */
    private array $succeeded = [];

    /** @var list<int> */
    private array $orders = [];

    private bool $listening = true;

    private string $currency = '';

    protected function setUp(): void
    {
        parent::setUp();
        if (!\function_exists('wc_create_order')) {
            self::markTestSkipped('WooCommerce is not installed.');
        }
        $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('payments'));
        $currency = \get_option('woocommerce_currency');
        $this->currency = \is_string($currency) ? $currency : 'IRR';
        \update_option('woocommerce_currency', 'IRT');
    }

    protected function tearDown(): void
    {
        $this->listening = false;
        if (\function_exists('wc_get_order')) {
            foreach ($this->orders as $id) {
                $order = \wc_get_order($id);
                if ($order instanceof \WC_Order) {
                    $order->delete(true);
                }
            }
            \update_option('woocommerce_currency', $this->currency);
            $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('payments'));
        }
        parent::tearDown();
    }

    public function testABookingBecomesAPendingOrderWithOneFeeLine(): void
    {
        $started = $this->service()->start(7, Money::ofRial(1_250_000), self::CALLBACK, [], true);
        $order = $this->order($started->payment->authority);

        self::assertSame(['pending', '125000'], [$order->get_status(), (string) (int) $order->get_total()]);
        self::assertCount(1, $order->get_items('fee'));
        self::assertSame('7', $order->get_meta(WcOrderStore::appointmentKey()));
        self::assertSame($order->get_checkout_payment_url(), $started->redirectUrl);
    }

    public function testAPaidOrderSettlesThePaymentOnceWithItsTransaction(): void
    {
        $service = $this->service();
        $started = $service->start(7, Money::ofRial(1_250_000), self::CALLBACK, [], true);
        $authority = $started->payment->authority;

        $unpaid = $service->settle(WooCommerceGateway::ID, $authority, ['order' => $authority]);
        self::assertSame(PaymentStatus::AwaitingCallback, $unpaid->status, 'A pending order is not a verdict.');

        $this->order($authority)->payment_complete('TX-42');
        $paid = $service->settle(WooCommerceGateway::ID, $authority, ['order' => $authority]);
        $service->settle(WooCommerceGateway::ID, $authority, ['order' => $authority]);

        self::assertSame([PaymentStatus::Succeeded, 'TX-42'], [$paid->status, $paid->refId]);
        self::assertCount(1, $this->succeeded);
    }

    public function testACancelledOrderFailsThePayment(): void
    {
        $service = $this->service();
        $authority = $service->start(7, Money::ofRial(1_250_000), self::CALLBACK, [], true)->payment->authority;

        $this->order($authority)->update_status('cancelled');

        self::assertSame(
            PaymentStatus::Failed,
            $service->settle(WooCommerceGateway::ID, $authority, [])->status
        );
    }

    public function testTheHooksSettleOnStatusChangeAndSendTheCustomerToTheCallback(): void
    {
        $service = $this->service();
        (new WooCommerceHooks(\VAQTYAR_FILE, function (string $orderId) use ($service): void {
            if ($this->listening) {
                $service->settle(WooCommerceGateway::ID, $orderId, []);
            }
        }))->register();
        $authority = $service->start(7, Money::ofRial(1_250_000), self::CALLBACK, [], true)->payment->authority;
        $order = $this->order($authority);

        $order->payment_complete('TX-43');

        self::assertCount(1, $this->succeeded, 'Paying the order is enough: the customer need not come back.');
        self::assertStringStartsWith(
            self::CALLBACK . '&order=' . $authority,
            $order->get_checkout_order_received_url()
        );
    }

    public function testAnOrderThatIsNotABookingsIsNotOurs(): void
    {
        $other = \wc_create_order();
        self::assertInstanceOf(\WC_Order::class, $other);
        $this->orders[] = $other->get_id();

        self::assertNull((new WcOrderStore())->find((string) $other->get_id()));
        self::assertNull((new WcOrderStore())->find('not-an-id'));
    }

    private function order(string $authority): \WC_Order
    {
        $order = \wc_get_order((int) $authority);
        self::assertInstanceOf(\WC_Order::class, $order);
        $this->orders[] = $order->get_id();

        return $order;
    }

    private function service(): PaymentService
    {
        $db = new Db($this->wpdb());
        $this->realDb();

        return new PaymentService(
            new GatewayRegistry([new WooCommerceGateway(new WcOrderStore())]),
            new WpdbPaymentRepository($db),
            new Transaction($db),
            new SystemClock(),
            new WpAuthorizer(),
            function (Payment $payment): void {
                $this->succeeded[] = $payment;
            }
        );
    }
}
