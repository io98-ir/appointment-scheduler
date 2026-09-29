<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

/**
 * A channel could not deliver a message. The text goes to the notification
 * log, so it must not hold the message or the recipient.
 */
final class DeliveryFailed extends \RuntimeException
{
}
