<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use Vaqtyar\Modules\Payments\Application\GatewayException;
use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Modules\Payments\Infrastructure\ZarinpalGateway;
use Vaqtyar\Shared\Domain\Money;

final class ZarinpalGatewayTest extends GatewayContractTest
{
    private int $serial = 0;

    /** @var array<mixed> what verify.json answers */
    private array $verifyAnswer = ['data' => [], 'errors' => ['code' => -51, 'message' => 'failed']];

    protected function gateway(): PaymentGateway
    {
        return new ZarinpalGateway($this->http(), 'merchant-1');
    }

    /**
     * @return array<string, string>
     */
    protected function unpaidParams(): array
    {
        return ['Authority' => 'A1', 'Status' => 'NOK'];
    }

    public function testItSendsRialsAndSendsTheCustomerToStartPay(): void
    {
        $http = $this->http();
        $started = (new ZarinpalGateway($http, 'merchant-1'))
            ->start(7, Money::ofRial(1_250_000), 'https://x.test/cb');

        self::assertSame('https://payment.zarinpal.com/pg/StartPay/' . $started->authority, $started->redirectUrl);
        self::assertSame(1_250_000, $http->calls[0]['body']['amount']);
        self::assertSame(['IRR', 'https://x.test/cb'], [
            $http->calls[0]['body']['currency'],
            $http->calls[0]['body']['callback_url'],
        ]);
    }

    public function testARefusedRequestFailsOverToTheNextGateway(): void
    {
        $gateway = new ZarinpalGateway(
            new FakeJsonHttp(static fn (): array => ['data' => [], 'errors' => ['code' => -9]]),
            'merchant-1'
        );

        $this->expectException(GatewayException::class);
        $gateway->start(7, Money::ofRial(1000), 'https://x.test/cb');
    }

    public function testTheCallbackNamesThePaymentByAuthority(): void
    {
        $gateway = $this->gateway();

        self::assertSame('A1', $gateway->callbackAuthority(['Authority' => 'A1', 'Status' => 'OK']));
        self::assertNull($gateway->callbackAuthority(['authority' => 'A1']));
    }

    public function testAVerifiedPaymentCarriesItsReferenceAndCard(): void
    {
        $this->verifyAnswer = [
            'data' => ['code' => 100, 'ref_id' => 201, 'card_pan' => '603799******1234'],
            'errors' => [],
        ];

        $verdict = $this->gateway()->verify('A1', Money::ofRial(1000), ['Authority' => 'A1', 'Status' => 'OK']);

        self::assertTrue($verdict->paid);
        self::assertSame(['201', '603799******1234'], [$verdict->refId, $verdict->cardMask]);
    }

    public function testAnAlreadyVerifiedPaymentStillCountsAsPaid(): void
    {
        $this->verifyAnswer = ['data' => ['code' => 101, 'ref_id' => 201], 'errors' => []];

        self::assertTrue($this->gateway()->verify('A1', Money::ofRial(1000), [])->paid);
    }

    public function testADefiniteNoIsNotPaidButNoAnswerAtAllIsAnError(): void
    {
        self::assertFalse($this->gateway()->verify('A1', Money::ofRial(1000), ['Status' => 'OK'])->paid);

        $this->verifyAnswer = [];
        $this->expectException(GatewayException::class);
        $this->gateway()->verify('A1', Money::ofRial(1000), ['Status' => 'OK']);
    }

    public function testACallbackThatSaysNokIsNeverAskedAbout(): void
    {
        $http = $this->http();

        (new ZarinpalGateway($http, 'merchant-1'))->verify('A1', Money::ofRial(1000), ['Status' => 'NOK']);

        self::assertSame([], $http->calls);
    }

    private function http(): FakeJsonHttp
    {
        return new FakeJsonHttp(function (string $url): array {
            if (\str_ends_with($url, 'request.json')) {
                return ['data' => ['code' => 100, 'authority' => 'A' . ++$this->serial], 'errors' => []];
            }

            return $this->verifyAnswer;
        });
    }
}
