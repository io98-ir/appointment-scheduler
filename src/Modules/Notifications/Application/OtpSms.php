<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

use Vaqtyar\Modules\Notifications\Domain\SmsPattern;

/**
 * Sends the login code as an SMS, by each provider's pattern when the owner
 * gave one (a line that sends free text often drops a message with a bare
 * number in it) and as plain text otherwise. The code is in the message and
 * nowhere else: a failure is reported without it.
 */
final class OtpSms
{
    public const TEXT = 'کد ورود شما: {code}';

    /**
     * @param \Closure(): SmsSender $sender
     */
    public function __construct(private readonly \Closure $sender, private readonly SmsConfigStore $config)
    {
    }

    /**
     * @param string $phone E.164.
     * @throws DeliveryFailed
     */
    public function send(string $phone, string $code): void
    {
        $sender = ($this->sender)();
        if (!$sender->isConfigured()) {
            return;
        }
        $patterns = [];
        foreach ($this->config->get()->otpPatterns as $provider => $pattern) {
            $patterns[$provider] = new SmsPattern($pattern, ['code']);
        }
        $sender->send($phone, \str_replace('{code}', $code, self::TEXT), $patterns, ['code' => $code]);
    }
}
