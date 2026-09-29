<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use Vaqtyar\Modules\Payments\Application\PaymentGateway;
use Vaqtyar\Modules\Payments\Infrastructure\OfflineGateway;
use Vaqtyar\Shared\Domain\Money;

final class OfflineGatewayTest extends GatewayContractTest
{
    protected function gateway(): PaymentGateway
    {
        return new OfflineGateway();
    }

    /**
     * @return array<string, string>
     */
    protected function unpaidParams(): array
    {
        // A callback's parameters are text: "1" or "true" is not the boolean staff pass.
        return ['confirmed' => '1', 'status' => 'OK'];
    }

    public function testThereIsNoPageToSendTheCustomerTo(): void
    {
        self::assertNull((new OfflineGateway())->start(1, Money::ofRial(1000), 'https://x.test')->redirectUrl);
    }
}
