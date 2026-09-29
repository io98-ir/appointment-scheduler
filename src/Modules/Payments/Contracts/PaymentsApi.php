<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Contracts;

use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Money;

/**
 * What Booking may ask of Payments (architecture §2 rule 4). The other
 * direction is an event: Payments fires `{prefix}/payments/succeeded`.
 */
interface PaymentsApi
{
    /**
     * Whether a customer can pay online, that is at a gateway with a page of its own.
     */
    public function onlineAvailable(): bool;

    /**
     * Opens an online payment and says where to send the customer.
     *
     * @param string $returnUrl where the customer lands after the gateway; a page of this site.
     * @return string the gateway's page.
     * @throws Conflict no_gateway_available when every online gateway refuses.
     */
    public function startOnline(int $appointmentId, Money $amount, string $returnUrl): string;
}
