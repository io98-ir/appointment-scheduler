<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

/**
 * The sms channel: a template's body as the plain text, or its pattern for
 * the provider that gets it. The log's reference is "provider:reference".
 */
final class SmsChannel implements NotificationChannel
{
    public const ID = 'sms';

    public function __construct(private readonly SmsSender $sender)
    {
    }

    public function id(): string
    {
        return self::ID;
    }

    public function address(): string
    {
        return 'phone';
    }

    public function send(Message $message): string
    {
        return $this->sender->send($message->recipient, $message->body, $message->smsPatterns, $message->values);
    }
}
