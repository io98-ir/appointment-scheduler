<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Policy\BookingTerms;

/**
 * What a service asks of a customer's own booking (BookingTerms): its own
 * policy of each type over the global one (service_id 0), and nothing asked
 * when neither is set.
 */
interface TermsReader
{
    public function termsFor(int $serviceId): BookingTerms;
}
