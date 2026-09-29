<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Notifications;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Notifications\Application\NotificationLog;
use Vaqtyar\Modules\Notifications\Domain\Audience;
use Vaqtyar\Modules\Notifications\Domain\Template;
use Vaqtyar\Modules\Notifications\Domain\Trigger;
use Vaqtyar\Modules\Notifications\Infrastructure\Persistence\WpdbNotificationLog;
use Vaqtyar\Modules\Notifications\Infrastructure\Persistence\WpdbTemplateRepository;
use Vaqtyar\Shared\SystemClock;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * The notification tables on a real database: the seeded templates, the
 * repository round trip and the dedup guard of the log.
 */
final class NotificationStoreTest extends TestCase
{
    use RealDatabase;

    public function testANewSiteStartsWithTheDefaultTemplates(): void
    {
        $repository = new WpdbTemplateRepository($this->realDb(), new SystemClock());

        self::assertGreaterThanOrEqual(7, \count($repository->all()));
        $reminders = $repository->enabledFor(Trigger::Reminder);
        self::assertNotSame([], $reminders);
        self::assertSame(1440, $reminders[0]->offsetMin);
    }

    public function testATemplateIsSavedChangedAndDeleted(): void
    {
        $repository = new WpdbTemplateRepository($this->realDb(), new SystemClock());

        $saved = $repository->save(new Template(null, Trigger::Booked, Audience::Admin, 'email', null, 'S', 'B'));
        self::assertNotNull($saved->id);

        $repository->save(new Template($saved->id, Trigger::Booked, Audience::Admin, 'email', null, 'S2', 'B2', false));
        $found = $repository->find($saved->id);
        self::assertNotNull($found);
        self::assertSame('S2', $found->subject);
        self::assertFalse($found->enabled);

        $repository->delete($saved->id);
        self::assertNull($repository->find($saved->id));
    }

    public function testAMessageIsClaimedOnceAndAFailedOneCanBeClaimedAgain(): void
    {
        $log = new WpdbNotificationLog($this->realDb());
        $this->realDb()->execute('DELETE FROM %i WHERE dedup_key = %s', Tables::name('notification_log'), 'test:1');
        $now = \time();

        self::assertTrue($log->claim('test:1', 1, 'email', 'ali@example.com', $now));
        self::assertFalse($log->claim('test:1', 1, 'email', 'ali@example.com', $now), 'Being sent.');

        $log->markFailed('test:1', 'mail down');
        self::assertTrue($log->claim('test:1', 1, 'email', 'ali@example.com', $now), 'A failed one is retried.');
        self::assertFalse($log->claim('test:1', 1, 'email', 'ali@example.com', $now));

        $log->markSent('test:1', 'email', '', $now);
        self::assertFalse($log->claim('test:1', 1, 'email', 'ali@example.com', $now), 'Sent.');

        $entry = $log->page(0, 5)->items[0];
        self::assertSame(NotificationLog::SENT, $entry['status']);
        self::assertSame('***@example.com', $entry['recipient']);

        $this->realDb()->execute('DELETE FROM %i WHERE dedup_key = %s', Tables::name('notification_log'), 'test:1');
    }
}
