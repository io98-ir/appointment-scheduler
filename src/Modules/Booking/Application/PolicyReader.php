<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Policy\PolicyEvaluator;

/**
 * The policies table (data-model §2): a service's own policy of each type
 * over the global one (service_id 0), and a lenient default when neither
 * is set.
 */
interface PolicyReader
{
    public function forService(int $serviceId): PolicyEvaluator;
}
