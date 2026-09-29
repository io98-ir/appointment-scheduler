<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

/**
 * A message ready to hand to a channel: the address in the channel's own
 * form (an email address, a phone number in E.164), a subject (which a
 * channel without subjects ignores) and a plain-text body.
 */
final class Message
{
    public function __construct(
        public readonly string $recipient,
        public readonly string $subject,
        public readonly string $body,
    ) {
    }
}
