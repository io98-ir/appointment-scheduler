<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

use Vaqtyar\Modules\Notifications\Domain\SmsPattern;

/**
 * A message ready to hand to a channel: the address in the channel's own
 * form (an email address, a phone number in E.164), a subject (which a
 * channel without subjects ignores) and a plain-text body.
 *
 * $values and $smsPatterns are for a channel that sends by pattern (SMS):
 * the placeholders' values, and the template's pattern for each provider.
 */
final class Message
{
    /**
     * @param array<string, string> $values placeholder name to text.
     * @param array<string, SmsPattern> $smsPatterns by SMS provider id.
     */
    public function __construct(
        public readonly string $recipient,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $values = [],
        public readonly array $smsPatterns = [],
    ) {
    }
}
