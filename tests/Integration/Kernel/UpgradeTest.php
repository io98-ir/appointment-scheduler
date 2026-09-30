<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migrator;
use Vaqtyar\Kernel\Log\CreateLogsTable;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Kernel\Plugin;
use Vaqtyar\Kernel\Rest\CreateRateLimitsTable;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Admin\AdminModule;
use Vaqtyar\Modules\Booking\BookingModule;
use Vaqtyar\Modules\Catalog\CatalogModule;
use Vaqtyar\Modules\Customers\CustomersModule;
use Vaqtyar\Modules\Notifications\Infrastructure\Persistence\DefaultTemplates;
use Vaqtyar\Modules\Notifications\NotificationsModule;
use Vaqtyar\Modules\Payments\PaymentsModule;
use Vaqtyar\Modules\Scheduling\SchedulingModule;
use Vaqtyar\Modules\Widget\WidgetModule;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * Updating the plugin on a site that has data (T6.5). No release of ours is
 * older than this schema, so the "previous version" is made by taking the
 * last step of two modules back: the template column and the SMS templates
 * of notifications, and the start index of appointments. A customer's own
 * template is in the table, as it would be after months of use. The real
 * Migrator then brings the schema up to date, as the first request after an
 * update does (Plugin::boot), and nothing the site owner had may change.
 */
final class UpgradeTest extends TestCase
{
    use RealDatabase;

    private const CUSTOM_BODY = 'A body the owner wrote themselves';

    private Db $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->realDb();
        (new Migrator($this->db))->migrate($this->migrations());
    }

    protected function tearDown(): void
    {
        // Whatever a failed assertion left behind, the schema ends current for the tests after it.
        (new Migrator($this->db))->migrate($this->migrations());
        parent::tearDown();
    }

    public function testAnOlderSchemaWithTheOwnersDataIsBroughtUpWithoutLosingIt(): void
    {
        $templates = Tables::name('notification_templates');
        $id = (int) $this->db->getVar(
            'SELECT id FROM %i WHERE channel = %s ORDER BY id LIMIT 1',
            $templates,
            'email'
        );
        self::assertGreaterThan(0, $id, 'the fresh site starts with email templates');
        $this->db->execute('UPDATE %i SET body = %s WHERE id = %d', $templates, self::CUSTOM_BODY, $id);
        $emailsBefore = $this->rowsOf($templates, 'email');

        $this->rollBackToThePreviousVersion();
        self::assertFalse((new Migrator($this->db))->isCurrent($this->migrations()));

        self::assertTrue((new Migrator($this->db))->migrate($this->migrations()));

        self::assertTrue((new Migrator($this->db))->isCurrent($this->migrations()));
        self::assertTrue($this->hasColumn($templates, 'sms_patterns'), 'the new column is there');
        self::assertTrue($this->hasIndex(Tables::name('appointments'), 'start_at'), 'the new index is there');
        self::assertSame(
            self::CUSTOM_BODY,
            $this->db->getVar('SELECT body FROM %i WHERE id = %d', $templates, $id),
            "the owner's own template is untouched"
        );
        self::assertSame($emailsBefore, $this->rowsOf($templates, 'email'), 'no email template was added or lost');
        self::assertSame(\count(DefaultTemplates::sms()), $this->rowsOf($templates, 'sms'));
    }

    public function testRunningTheUpdateAgainChangesNothing(): void
    {
        $templates = Tables::name('notification_templates');
        $this->rollBackToThePreviousVersion();
        $migrator = new Migrator($this->db);
        $migrator->migrate($this->migrations());
        $smsAfterFirst = $this->rowsOf($templates, 'sms');

        // An update that failed half-way runs its migrations again from the start.
        foreach ($this->migrations()['notifications'] as $migration) {
            $migration->up($this->db);
        }
        foreach ($this->migrations()['booking'] as $migration) {
            $migration->up($this->db);
        }

        self::assertSame($smsAfterFirst, $this->rowsOf($templates, 'sms'), 'the SMS templates are seeded once');
        self::assertTrue($migrator->isCurrent($this->migrations()));
    }

    private function rollBackToThePreviousVersion(): void
    {
        $templates = Tables::name('notification_templates');
        $this->db->execute('DELETE FROM %i WHERE channel = %s', $templates, 'sms');
        if ($this->hasColumn($templates, 'sms_patterns')) {
            $this->db->execute('ALTER TABLE %i DROP COLUMN sms_patterns', $templates);
        }
        $appointments = Tables::name('appointments');
        if ($this->hasIndex($appointments, 'start_at')) {
            $this->db->execute('ALTER TABLE %i DROP KEY start_at', $appointments);
        }
        $versions = $this->currentVersions();
        $versions['notifications'] = 1;
        $versions['booking'] = 3;
        \update_option(Options::key(Migrator::OPTION), $versions, true);
    }

    /**
     * @return array<string, int>
     */
    private function currentVersions(): array
    {
        $stored = \get_option(Options::key(Migrator::OPTION), []);

        $versions = [];
        foreach (\is_array($stored) ? $stored : [] as $owner => $count) {
            $versions[(string) $owner] = \is_numeric($count) ? (int) $count : 0;
        }

        return $versions;
    }

    /**
     * What Plugin::migrations() lists: the kernel's tables, then each module's.
     *
     * @return array<string, list<\Vaqtyar\Kernel\Database\Migration>>
     */
    private function migrations(): array
    {
        $migrations = [Plugin::KERNEL_ID => [new CreateRateLimitsTable(), new CreateLogsTable()]];
        $modules = [
            new CatalogModule(),
            new SchedulingModule(),
            new CustomersModule(),
            new BookingModule(),
            new PaymentsModule(),
            new NotificationsModule(),
            new AdminModule(),
            new WidgetModule(),
        ];
        foreach ($modules as $module) {
            if ([] !== $module->migrations()) {
                $migrations[$module->id()] = $module->migrations();
            }
        }

        return $migrations;
    }

    private function rowsOf(string $table, string $channel): int
    {
        return (int) $this->db->getVar('SELECT COUNT(*) FROM %i WHERE channel = %s', $table, $channel);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return '0' !== $this->db->getVar(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            $table,
            $column
        );
    }

    private function hasIndex(string $table, string $index): bool
    {
        return '0' !== $this->db->getVar(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s',
            $table,
            $index
        );
    }
}
