<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure;

use Vaqtyar\Modules\Notifications\Application\DeliveryFailed;
use Vaqtyar\Modules\Notifications\Application\Message;
use Vaqtyar\Modules\Notifications\Application\NotificationChannel;

/**
 * Email through wp_mail(), so whatever SMTP plugin the site uses applies.
 * Plain text: a template holds no markup, and a customer's name in it
 * cannot inject any.
 */
final class EmailChannel implements NotificationChannel
{
    public const ID = 'email';

    public function id(): string
    {
        return self::ID;
    }

    public function address(): string
    {
        return 'email';
    }

    public function send(Message $message): string
    {
        if (!\is_email($message->recipient)) {
            throw new DeliveryFailed('The recipient is not an email address.');
        }
        // A site plugin that makes all mail HTML would otherwise render a customer's
        // name as markup, so the content type is ours for this one message.
        $plain = static fn (): string => 'text/plain';
        \add_filter('wp_mail_content_type', $plain, \PHP_INT_MAX);
        try {
            $sent = \wp_mail($message->recipient, $message->subject, $message->body);
        } finally {
            \remove_filter('wp_mail_content_type', $plain, \PHP_INT_MAX);
        }
        if (!$sent) {
            throw new DeliveryFailed('wp_mail() could not send the message.');
        }

        return '';
    }
}
