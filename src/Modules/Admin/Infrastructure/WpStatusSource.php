<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Infrastructure;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migrator;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Admin\Application\StatusSource;
use Vaqtyar\Modules\Admin\Domain\HealthEvaluator;
use Vaqtyar\Modules\Admin\Domain\HealthFacts;
use Vaqtyar\Shared\Domain\Clock;

/**
 * StatusSource on the running site: PHP, WordPress, MySQL, Action
 * Scheduler's tables and the plugin's log.
 */
final class WpStatusSource implements StatusSource
{
    /** Failures older than this are history, not news. */
    private const FAILED_WINDOW_DAYS = 7;

    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly string $pluginVersion,
    ) {
    }

    public function facts(): HealthFacts
    {
        [$pending, $late, $failed] = $this->queue();

        return new HealthFacts(
            \extension_loaded('intl'),
            \function_exists('sodium_crypto_secretbox'),
            $this->isInnoDb(),
            self::tehranOffset('2026-01-15'),
            self::tehranOffset('2026-07-15'),
            \defined('DISABLE_WP_CRON') && \DISABLE_WP_CRON,
            $pending,
            $late,
            $failed,
        );
    }

    /**
     * @return array{plugin: string, wordpress: string, php: string, database: string}
     */
    public function versions(): array
    {
        return [
            'plugin' => $this->pluginVersion,
            'wordpress' => \get_bloginfo('version'),
            'php' => \PHP_VERSION,
            'database' => (string) $this->db->getVar('SELECT VERSION()'),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function schema(): array
    {
        $stored = \get_option(Options::key(Migrator::OPTION), []);
        $schema = [];
        foreach (\is_array($stored) ? $stored : [] as $owner => $count) {
            if (\is_string($owner) && \is_int($count)) {
                $schema[$owner] = $count;
            }
        }

        return $schema;
    }

    /**
     * @return list<array{at: string, channel: string, message: string}>
     */
    public function recentErrors(int $limit): array
    {
        $rows = $this->db->getResults(
            "SELECT created_at, channel, message FROM %i WHERE level = 'error' ORDER BY id DESC LIMIT %d",
            Tables::name('logs'),
            $limit
        );

        return \array_map(
            static fn (array $row): array => [
                'at' => (string) $row['created_at'],
                'channel' => (string) $row['channel'],
                'message' => (string) $row['message'],
            ],
            $rows
        );
    }

    private function isInnoDb(): bool
    {
        $engine = $this->db->getVar(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            Tables::name('logs')
        );

        return null !== $engine && 0 === \strcasecmp($engine, 'InnoDB');
    }

    private static function tehranOffset(string $date): ?int
    {
        try {
            return (new \DateTimeZone('Asia/Tehran'))->getOffset(new \DateTimeImmutable($date . ' 12:00:00 UTC'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Only the plugin's own actions: the store may hold other plugins' jobs.
     *
     * @return array{int, int, int} Pending, late, failed.
     */
    private function queue(): array
    {
        $table = $this->actionsTable();
        if (null === $table) {
            return [0, 0, 0];
        }
        $now = $this->clock->now();
        $mine = Identity::HOOK_PREFIX . '/%';
        $late = $now->modify('-' . HealthEvaluator::QUEUE_LATE_SECONDS . ' seconds')->format('Y-m-d H:i:s');
        $since = $now->modify('-' . self::FAILED_WINDOW_DAYS . ' days')->format('Y-m-d H:i:s');

        $pending = (int) $this->db->getVar(
            "SELECT COUNT(*) FROM %i WHERE status = 'pending' AND hook LIKE %s",
            $table,
            $mine
        );
        $overdue = (int) $this->db->getVar(
            "SELECT COUNT(*) FROM %i WHERE status = 'pending' AND hook LIKE %s AND scheduled_date_gmt < %s",
            $table,
            $mine,
            $late
        );
        $failed = (int) $this->db->getVar(
            "SELECT COUNT(*) FROM %i WHERE status = 'failed' AND hook LIKE %s AND scheduled_date_gmt >= %s",
            $table,
            $mine,
            $since
        );

        return [$pending, $overdue, $failed];
    }

    private function actionsTable(): ?string
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;
        if (!$wpdb instanceof \wpdb) {
            return null;
        }
        $table = $wpdb->prefix . 'actionscheduler_actions';
        $found = $this->db->getVar(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            $table
        );

        return null === $found ? null : $table;
    }
}
