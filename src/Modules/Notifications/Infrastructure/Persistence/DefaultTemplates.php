<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Persistence;

use Vaqtyar\Modules\Notifications\Domain\Audience;
use Vaqtyar\Modules\Notifications\Domain\Template;
use Vaqtyar\Modules\Notifications\Domain\Trigger;

/**
 * The templates a new site starts with, in Persian: stored as data, so the
 * owner edits them like any other. all() is the email set, sms() the SMS one.
 */
final class DefaultTemplates
{
    /**
     * The customer's SMS: short, no subject, one message per event.
     *
     * @return list<Template>
     */
    public static function sms(): array
    {
        $details = '{service} - {date} ساعت {time}';

        return [
            new Template(
                null,
                Trigger::Booked,
                Audience::Customer,
                'sms',
                null,
                '',
                "{customer_name} عزیز، نوبت شما ثبت شد.\n" . $details . "\nکد پیگیری: {code}"
            ),
            new Template(
                null,
                Trigger::Cancelled,
                Audience::Customer,
                'sms',
                null,
                '',
                "{customer_name} عزیز، نوبت شما لغو شد.\n" . $details . "\nکد پیگیری: {code}"
            ),
            new Template(
                null,
                Trigger::Rescheduled,
                Audience::Customer,
                'sms',
                null,
                '',
                "{customer_name} عزیز، زمان نوبت شما تغییر کرد.\nزمان جدید: " . $details . "\nکد پیگیری: {code}"
            ),
            new Template(
                null,
                Trigger::Reminder,
                Audience::Customer,
                'sms',
                3 * 60,
                '',
                "{customer_name} عزیز، یادآوری نوبت شما:\n" . $details
            ),
        ];
    }

    /**
     * @return list<Template>
     */
    public static function all(): array
    {
        $details = "{service} - {date} ساعت {time}\nکد پیگیری: {code}";

        return [
            new Template(
                null,
                Trigger::Booked,
                Audience::Customer,
                'email',
                null,
                'نوبت شما ثبت شد',
                "سلام {customer_name}،\nنوبت شما ثبت شد.\n" . $details
            ),
            new Template(
                null,
                Trigger::Cancelled,
                Audience::Customer,
                'email',
                null,
                'نوبت شما لغو شد',
                "سلام {customer_name}،\nنوبت شما لغو شد.\n" . $details
            ),
            new Template(
                null,
                Trigger::Rescheduled,
                Audience::Customer,
                'email',
                null,
                'زمان نوبت شما تغییر کرد',
                "سلام {customer_name}،\nزمان نوبت شما تغییر کرد.\nزمان جدید:\n" . $details
            ),
            new Template(
                null,
                Trigger::Reminder,
                Audience::Customer,
                'email',
                24 * 60,
                'یادآوری نوبت',
                "سلام {customer_name}،\nیادآوری نوبت شما:\n" . $details
            ),
            new Template(
                null,
                Trigger::Booked,
                Audience::Staff,
                'email',
                null,
                'نوبت جدید',
                "نوبت جدید: {customer_name}\n" . $details
            ),
            new Template(
                null,
                Trigger::Cancelled,
                Audience::Staff,
                'email',
                null,
                'نوبت لغو شد',
                "نوبت لغو شد: {customer_name}\n" . $details
            ),
            new Template(
                null,
                Trigger::Booked,
                Audience::Admin,
                'email',
                null,
                'نوبت جدید ({code})',
                "نوبت جدید: {customer_name}\nکارمند: {staff}\n" . $details
            ),
        ];
    }
}
