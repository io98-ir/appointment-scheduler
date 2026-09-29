<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

use Vaqtyar\Modules\Notifications\Domain\SmsCatalog;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * How the SMS providers are used: the failover order (a provider that is
 * not listed is off), the sender line of each, and the pattern code each
 * has for the login code (its one value is {code}).
 */
final class SmsConfig
{
    /**
     * @param list<string> $order provider ids.
     * @param array<string, string> $senders by provider id.
     * @param array<string, string> $otpPatterns pattern code by provider id.
     * @throws InvalidValue invalid_sms_config
     */
    public function __construct(
        public readonly array $order = SmsCatalog::IDS,
        public readonly array $senders = [],
        public readonly array $otpPatterns = [],
    ) {
        if (\count(\array_unique($order)) !== \count($order)) {
            throw new InvalidValue('invalid_sms_config', 'A provider is listed once.');
        }
        foreach ([...$order, ...\array_keys($senders), ...\array_keys($otpPatterns)] as $id) {
            if (!\in_array($id, SmsCatalog::IDS, true)) {
                throw new InvalidValue('invalid_sms_config', 'No SMS provider has this id.');
            }
        }
        foreach ($senders as $sender) {
            if (1 !== \preg_match('/^\+?[0-9A-Za-z]{1,32}$/D', $sender)) {
                throw new InvalidValue('invalid_sms_config', 'A sender line is digits or letters, up to 32.');
            }
        }
        foreach ($otpPatterns as $code) {
            if (1 !== \preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $code)) {
                throw new InvalidValue('invalid_sms_config', 'A pattern code is letters, digits, - and _.');
            }
        }
    }

    public function sender(string $provider): string
    {
        return $this->senders[$provider] ?? '';
    }
}
