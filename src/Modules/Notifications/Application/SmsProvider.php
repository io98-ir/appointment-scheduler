<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

/**
 * One SMS provider's API. It is given an Iranian mobile number as 09121234567
 * (SmsSender has checked it) and says what went wrong with DeliveryFailed.
 */
interface SmsProvider
{
    public function id(): string;

    /**
     * @return string the provider's reference for the message.
     * @throws DeliveryFailed
     */
    public function send(string $mobile, string $text): string;

    /**
     * @param string $code the provider's pattern code.
     * @param array<string, string> $args the pattern's values by placeholder name, in the pattern's order.
     * @return string the provider's reference for the message.
     * @throws DeliveryFailed
     */
    public function sendPattern(string $mobile, string $code, array $args): string;
}
