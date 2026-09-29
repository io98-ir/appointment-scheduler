<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

/**
 * Where a message goes out: email now, SMS providers with T5.5. Registered
 * by id, the same word a template's channel holds.
 */
interface NotificationChannel
{
    public function id(): string;

    /**
     * What the channel needs the recipient to be: "email" or "phone".
     */
    public function address(): string;

    /**
     * @return string the provider's reference for the message, empty when it has none.
     * @throws DeliveryFailed
     */
    public function send(Message $message): string;
}
