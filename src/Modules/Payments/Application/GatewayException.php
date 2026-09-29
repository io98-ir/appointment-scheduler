<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

/**
 * A gateway could not be reached or refused a request. The message is for
 * the log; nothing from the gateway reaches the customer.
 */
final class GatewayException extends \RuntimeException
{
}
