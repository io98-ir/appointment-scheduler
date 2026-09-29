<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use Vaqtyar\Modules\Payments\Application\GatewayException;
use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Modules\Payments\Infrastructure\ZibalGateway;
use Vaqtyar\Shared\Domain\Money;

final class ZibalGatewayTest extends GatewayContractTest
{
    private int $serial = 1000;

    /** @var array<mixed> what verify answers */
    private array $verifyAnswer = ['result' => 202, 'message' => 'not paid'];

    protected function gateway(): PaymentGateway
    {
        return new ZibalGateway($this->http(), 'zibal');
    }

    /**
     * @return array<string, string>
     */
    protected function unpaidParams(): array
    {
        return ['trackId' => '1001', 'success' => '0', 'status' => '3'];
    }

    public function testItSendsRialsAndSendsTheCustomerToTheStartPage(): void
    {
        $http = $this->http();
        $started = (new ZibalGateway($http, 'zibal'))->start(7, Money::ofRial(750_000), 'https://x.test/cb');

        self::assertSame('https://gateway.zibal.ir/start/' . $started->authority, $started->redirectUrl);
        self::assertSame([750_000, 'https://x.test/cb'], [
            $http->calls[0]['body']['amount'],
            $http->calls[0]['body']['callbackUrl'],
        ]);
    }

    public function testARefusedRequestIsAGatewayError(): void
    {
        $gateway = new ZibalGateway(new FakeJsonHttp(static fn (): array => ['result' => 102]), 'bad');

        $this->expectException(GatewayException::class);
        $gateway->start(7, Money::ofRial(1000), 'https://x.test/cb');
    }

    public function testTheCallbackNamesThePaymentByTrackId(): void
    {
        self::assertSame('1001', $this->gateway()->callbackAuthority(['trackId' => '1001', 'success' => '1']));
        self::assertNull($this->gateway()->callbackAuthority(['success' => '1']));
    }

    public function testAVerifiedPaymentCarriesItsReferenceAndCard(): void
    {
        $this->verifyAnswer = ['result' => 100, 'amount' => 1000, 'refNumber' => 555, 'cardNumber' => '6037****1234'];

        $verdict = $this->gateway()->verify('1001', Money::ofRial(1000), ['success' => '1']);

        self::assertTrue($verdict->paid);
        self::assertSame(['555', '6037****1234'], [$verdict->refId, $verdict->cardMask]);
    }

    public function testAnAmountThatIsNotOursIsNotPaid(): void
    {
        $this->verifyAnswer = ['result' => 100, 'amount' => 10, 'refNumber' => 555];

        self::assertFalse($this->gateway()->verify('1001', Money::ofRial(1000), [])->paid);
    }

    public function testAnAlreadyVerifiedPaymentStillCountsAsPaidAndAnUnpaidOneDoesNot(): void
    {
        $this->verifyAnswer = ['result' => 201, 'amount' => 1000, 'refNumber' => 555];
        self::assertTrue($this->gateway()->verify('1001', Money::ofRial(1000), [])->paid);

        $this->verifyAnswer = ['result' => 202];
        self::assertFalse($this->gateway()->verify('1001', Money::ofRial(1000), [])->paid);
    }

    public function testAnAnswerWithoutAResultIsAnError(): void
    {
        $this->verifyAnswer = ['message' => 'oops'];

        $this->expectException(GatewayException::class);
        $this->gateway()->verify('1001', Money::ofRial(1000), []);
    }

    private function http(): FakeJsonHttp
    {
        return new FakeJsonHttp(function (string $url): array {
            if (\str_ends_with($url, '/request')) {
                return ['result' => 100, 'trackId' => ++$this->serial];
            }

            return $this->verifyAnswer;
        });
    }
}
