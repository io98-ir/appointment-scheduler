<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

use Vaqtyar\Shared\Domain\Page;

/**
 * What was sent, and the guard that a message goes out once: the dedup key
 * is unique, so a repeated job finds its message already claimed.
 *
 * @phpstan-type Entry array{
 *     id: int, template_id: int, channel: string, recipient: string, status: string,
 *     provider_ref: ?string, error: ?string, sent_at: ?int, created_at: int
 * }
 */
interface NotificationLog
{
    public const SENDING = 'sending';
    public const SENT = 'sent';
    public const FAILED = 'failed';

    /**
     * Takes the right to send this message: true for a new key, and for one
     * whose earlier attempt failed (it may be tried again); false when it
     * is being sent or was sent.
     *
     * @param string $recipient the address as used; the log keeps it masked.
     */
    public function claim(string $dedupKey, int $templateId, string $channel, string $recipient, int $now): bool;

    public function markSent(string $dedupKey, string $provider, string $reference, int $now): void;

    public function markFailed(string $dedupKey, string $error): void;

    /**
     * Newest first.
     *
     * @return Page<Entry>
     */
    public function page(int $offset, int $limit): Page;
}
