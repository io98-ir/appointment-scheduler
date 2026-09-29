<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Log\Pii;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Notifications\Application\NotificationLog;
use Vaqtyar\Shared\Domain\Page;

/**
 * NotificationLog on the notification_log table. The unique dedup key is
 * the guard: a claim is an INSERT that a second one cannot repeat.
 */
final class WpdbNotificationLog implements NotificationLog
{
    private const ERROR_MAX = 191;

    public function __construct(private readonly Db $db)
    {
    }

    public function claim(string $dedupKey, int $templateId, string $channel, string $recipient, int $now): bool
    {
        $at = \gmdate('Y-m-d H:i:s', $now);
        $inserted = $this->db->execute(
            'INSERT IGNORE INTO %i (dedup_key, template_id, channel, recipient_masked, status, created_at)
            VALUES (%s, %d, %s, %s, %s, %s)',
            Tables::name('notification_log'),
            $dedupKey,
            $templateId,
            $channel,
            self::mask($recipient),
            self::SENDING,
            $at
        );
        if ($inserted > 0) {
            return true;
        }

        // The compare-and-set lets exactly one of two retries take a failed message.
        return $this->db->execute(
            'UPDATE %i SET status = %s, error = NULL WHERE dedup_key = %s AND status = %s',
            Tables::name('notification_log'),
            self::SENDING,
            $dedupKey,
            self::FAILED
        ) > 0;
    }

    public function markSent(string $dedupKey, string $provider, string $reference, int $now): void
    {
        $this->db->update(
            Tables::name('notification_log'),
            [
                'status' => self::SENT,
                'provider' => $provider,
                'provider_ref' => '' === $reference ? null : \substr($reference, 0, self::ERROR_MAX),
                'sent_at' => \gmdate('Y-m-d H:i:s', $now),
            ],
            ['dedup_key' => $dedupKey]
        );
    }

    public function markFailed(string $dedupKey, string $error): void
    {
        $this->db->update(
            Tables::name('notification_log'),
            ['status' => self::FAILED, 'error' => \mb_substr($error, 0, self::ERROR_MAX)],
            ['dedup_key' => $dedupKey]
        );
    }

    public function page(int $offset, int $limit): Page
    {
        $rows = $this->db->getResults(
            'SELECT id, template_id, channel, recipient_masked, status, provider_ref, error, sent_at, created_at
            FROM %i ORDER BY id DESC LIMIT %d OFFSET %d',
            Tables::name('notification_log'),
            $limit,
            $offset
        );
        $entries = [];
        foreach ($rows as $values) {
            $row = new Row($values);
            $sentAt = $row->stringOrNull('sent_at');
            $entries[] = [
                'id' => $row->int('id'),
                'template_id' => $row->int('template_id'),
                'channel' => $row->string('channel'),
                'recipient' => $row->string('recipient_masked'),
                'status' => $row->string('status'),
                'provider_ref' => $row->stringOrNull('provider_ref'),
                'error' => $row->stringOrNull('error'),
                'sent_at' => null === $sentAt ? null : self::timestamp($sentAt),
                'created_at' => self::timestamp($row->string('created_at')),
            ];
        }
        $total = $this->db->getVar('SELECT COUNT(*) FROM %i', Tables::name('notification_log'));

        return new Page($entries, \is_numeric($total) ? (int) $total : 0);
    }

    /**
     * An email keeps its domain (Pii), a phone its last four digits; anything else is hidden.
     */
    private static function mask(string $recipient): string
    {
        $masked = Pii::mask($recipient);

        return $masked === $recipient ? '***' : $masked;
    }

    private static function timestamp(string $utc): int
    {
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->getTimestamp();
    }
}
