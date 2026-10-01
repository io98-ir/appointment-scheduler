<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

use Vaqtyar\Modules\Customers\Contracts\CustomerDirectory;

/**
 * Tells a customer on the waiting list, by SMS, that a time opened on the day they asked about.
 * Plain text, since a message with a link is the one a pattern would not carry anyway; without an
 * SMS provider nothing is sent and the owner sees only the log.
 */
final class WaitlistSms
{
    public const TEXT = 'برای روز {date} وقت خالی شد. برای رزرو: {url}';

    /**
     * @param \Closure(): SmsSender $sender
     * @param \Closure(int): string $dateText the day of a start (UTC seconds) in the site's calendar.
     */
    public function __construct(
        private readonly \Closure $sender,
        private readonly CustomerDirectory $customers,
        private readonly \Closure $dateText,
    ) {
    }

    /**
     * @param int $firstStart UTC seconds of the earliest time offered.
     * @return bool whether a message went out.
     * @throws DeliveryFailed
     */
    public function send(int $customerId, int $firstStart, string $pageUrl): bool
    {
        $sender = ($this->sender)();
        $phone = ($this->customers->summaries([$customerId])[$customerId] ?? null)?->phone;
        if (!$sender->isConfigured() || null === $phone) {
            return false;
        }
        $sender->send($phone, \strtr(self::TEXT, ['{date}' => ($this->dateText)($firstStart), '{url}' => $pageUrl]));

        return true;
    }
}
