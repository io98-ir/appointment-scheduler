<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Database;

use Vaqtyar\Kernel\Options;
use Vaqtyar\Kernel\Tables;

/**
 * Brings the schema up to date (data-model §3).
 *
 * Each owner (a module id) has an ordered, append-only list of migrations; its
 * version is how many of them have run. The versions of all owners live in one
 * autoloaded option, so checking for pending work costs no query. Tables and
 * options are per site, so on multisite every site migrates itself on its
 * first request after an update.
 */
final class Migrator
{
    public const OPTION = 'db_versions';

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * @param array<string, list<Migration>> $migrations Owner id => migrations, oldest first.
     */
    public function isCurrent(array $migrations): bool
    {
        $versions = $this->versions();
        foreach ($migrations as $owner => $list) {
            if (\count($list) > ($versions[$owner] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Runs the pending migrations in order and records each one as it
     * finishes, so a failure keeps the progress before it.
     *
     * A newer version than the list knows (the plugin was downgraded) runs
     * nothing: migrations have no down().
     *
     * @param array<string, list<Migration>> $migrations Owner id => migrations, oldest first.
     * @return bool False when another request is migrating right now; nothing ran.
     */
    public function migrate(array $migrations): bool
    {
        if ($this->isCurrent($migrations)) {
            return true;
        }
        if (!$this->acquireLock()) {
            return false;
        }

        try {
            // Read again under the lock: the request that held it may have finished
            // the work. The option is autoloaded, so without clearing the caches
            // this would return the copy loaded at the start of this request.
            \wp_cache_delete('alloptions', 'options');
            \wp_cache_delete('notoptions', 'options');
            \wp_cache_delete(Options::key(self::OPTION), 'options');
            $versions = $this->versions();
            foreach ($migrations as $owner => $list) {
                for ($i = $versions[$owner] ?? 0; $i < \count($list); $i++) {
                    $list[$i]->up($this->db);
                    $versions[$owner] = $i + 1;
                    \update_option(Options::key(self::OPTION), $versions, true);
                }
            }
        } finally {
            $this->releaseLock();
        }

        return true;
    }

    /**
     * @return array<string, int>
     */
    private function versions(): array
    {
        $stored = \get_option(Options::key(self::OPTION), []);
        if (!\is_array($stored)) {
            return [];
        }

        $versions = [];
        foreach ($stored as $owner => $version) {
            if (\is_string($owner) && \is_int($version)) {
                $versions[$owner] = $version;
            }
        }

        return $versions;
    }

    /**
     * Named locks are server-wide, so the name includes the database and the
     * site's table prefix. SHA1 keeps it within MySQL's 64 characters.
     */
    private function acquireLock(): bool
    {
        // 0: do not wait. The request holding the lock is already doing the work.
        return '1' === $this->db->getVar('SELECT GET_LOCK(SHA1(CONCAT(DATABASE(), %s)), 0)', $this->lockName());
    }

    private function releaseLock(): void
    {
        $this->db->getVar('SELECT RELEASE_LOCK(SHA1(CONCAT(DATABASE(), %s)))', $this->lockName());
    }

    private function lockName(): string
    {
        // Not a table: the site's prefixed name makes the lock per site.
        return '.' . Tables::name('migrate');
    }
}
