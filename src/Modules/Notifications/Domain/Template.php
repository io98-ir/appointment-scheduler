<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * One message the site sends: for a trigger, to an audience, over a channel.
 * The subject and body may hold {placeholders} (TemplateRenderer). A
 * reminder needs offsetMin, the minutes before the start; any other trigger
 * must not have one.
 */
final class Template
{
    public const MAX_OFFSET_MIN = 60 * 24 * 30;
    public const MAX_SUBJECT = 191;
    public const MAX_BODY = 2000;

    /**
     * @throws InvalidValue invalid_offset, invalid_subject, invalid_body or invalid_channel.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly Trigger $trigger,
        public readonly Audience $audience,
        public readonly string $channel,
        public readonly ?int $offsetMin,
        public readonly string $subject,
        public readonly string $body,
        public readonly bool $enabled = true,
    ) {
        if (1 !== \preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $channel)) {
            throw new InvalidValue('invalid_channel', 'A channel is a short lowercase name.');
        }
        if (Trigger::Reminder === $trigger) {
            if (null === $offsetMin || $offsetMin < 1 || $offsetMin > self::MAX_OFFSET_MIN) {
                throw new InvalidValue('invalid_offset', 'A reminder goes out 1 minute to 30 days before the start.');
            }
        } elseif (null !== $offsetMin) {
            throw new InvalidValue('invalid_offset', 'Only a reminder has an offset.');
        }
        if (\mb_strlen($subject) > self::MAX_SUBJECT) {
            throw new InvalidValue('invalid_subject', 'The subject is too long.');
        }
        if ('' === \trim($body) || \mb_strlen($body) > self::MAX_BODY) {
            throw new InvalidValue(
                'invalid_body',
                'The body is required and at most ' . self::MAX_BODY . ' characters.'
            );
        }
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->trigger,
            $this->audience,
            $this->channel,
            $this->offsetMin,
            $this->subject,
            $this->body,
            $this->enabled
        );
    }
}
