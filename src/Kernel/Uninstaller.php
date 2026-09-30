<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Settings\DataSettings;
use Vaqtyar\Kernel\Settings\Settings;

/**
 * What uninstall.php does when the plugin is deleted (architecture §5): the
 * site owner's bookings are theirs, so nothing is removed unless they ticked
 * "delete all data" (DataSettings). Then, for each site:
 *
 *  - the capabilities, from every role;
 *  - our pending and finished Action Scheduler jobs (its tables are shared);
 *  - every table starting with {prefix}{PREFIX}_;
 *  - every option, and transient, starting with {PREFIX}_ (last, so a
 *    failure half-way leaves the choice in place for another try).
 *
 * Found by prefix, not by a list, so a module added later is cleaned too and
 * uninstall does not need the modules loaded.
 */
final class Uninstaller
{
    public function __construct(private readonly Db $db, private readonly Settings $settings)
    {
    }

    /**
     * Every site of a network, each by its own choice.
     */
    public static function run(): void
    {
        $uninstaller = new self(Db::fromGlobals(), new Settings());
        if (!\is_multisite()) {
            $uninstaller->removeSite();

            return;
        }
        foreach (\get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
            \switch_to_blog((int) $siteId);
            try {
                $uninstaller->removeSite();
            } finally {
                \restore_current_blog();
            }
        }
    }

    /**
     * @return bool Whether the site's data was removed (the owner opted in).
     */
    public function removeSite(): bool
    {
        if (!$this->settings->get(DataSettings::class)->deleteOnUninstall) {
            return false;
        }

        $this->removeCapabilities();
        $this->removeScheduledJobs();
        $this->dropTables();
        $this->removeOptions();

        return true;
    }

    private function removeCapabilities(): void
    {
        $prefix = Identity::PREFIX . '_';
        foreach (\wp_roles()->role_objects as $role) {
            foreach (\array_keys($role->capabilities) as $capability) {
                if (\str_starts_with((string) $capability, $prefix)) {
                    $role->remove_cap((string) $capability);
                }
            }
        }
    }

    private function removeScheduledJobs(): void
    {
        $actions = Tables::external('actionscheduler_actions');
        if (!$this->tableExists($actions)) {
            return;
        }
        $hooks = self::like(Identity::HOOK_PREFIX . '/');
        $logs = Tables::external('actionscheduler_logs');
        if ($this->tableExists($logs)) {
            $this->db->execute(
                'DELETE l FROM %i l INNER JOIN %i a ON a.action_id = l.action_id WHERE a.hook LIKE %s',
                $logs,
                $actions,
                $hooks
            );
        }
        $this->db->execute('DELETE FROM %i WHERE hook LIKE %s', $actions, $hooks);
    }

    private function dropTables(): void
    {
        foreach ($this->db->getResults('SHOW TABLES LIKE %s', self::like(Tables::prefix())) as $row) {
            $name = \array_values($row)[0] ?? null;
            if (\is_string($name)) {
                $this->db->execute('DROP TABLE IF EXISTS %i', $name);
            }
        }
    }

    private function removeOptions(): void
    {
        $this->db->execute(
            'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s',
            Tables::external('options'),
            self::like(Identity::PREFIX . '_'),
            self::like('_transient_' . Identity::PREFIX . '_'),
            self::like('_transient_timeout_' . Identity::PREFIX . '_')
        );
        // Options are cached as a whole (autoload) and one by one.
        \wp_cache_delete('alloptions', 'options');
        \wp_cache_delete('notoptions', 'options');
    }

    private function tableExists(string $table): bool
    {
        return null !== $this->db->getVar('SHOW TABLES LIKE %s', self::literal($table));
    }

    /**
     * A LIKE pattern for names starting with $prefix.
     */
    private static function like(string $prefix): string
    {
        return self::literal($prefix) . '%';
    }

    /**
     * $text for a LIKE, with "_" and "%" meaning themselves (what $wpdb->esc_like() does).
     */
    private static function literal(string $text): string
    {
        return \addcslashes($text, '_%\\');
    }
}
