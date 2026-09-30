<?php

/**
 * The uninstall job's check (T6.5): runs the real uninstall.php inside wp-env,
 * where the plugin is active and its tables, options, capabilities and
 * Action Scheduler jobs exist. Run with `wp eval-file tests/Uninstall/run.php keep`,
 * then with `delete`; the second leaves the site without the plugin's data, so
 * the job ends there. Exits 1 with the reason on stderr, prints "UNINSTALL OK <mode>".
 *
 *   keep    the owner did not opt in: uninstall removes nothing.
 *   delete  the owner opted in: our data is gone, everything else is untouched.
 *
 * Not a PHPUnit test: dropping the plugin's tables would break every test after it.
 * No strict_types: wp eval-file runs the file through eval(), where the declaration is a fatal error.
 */

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Kernel\Settings\DataSettings;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Kernel\Tables;

// A closure, so nothing leaks into the globals of wp eval-file.
(static function (): void {
    // The last word of the command line: wp eval-file tests/Uninstall/run.php <mode>.
    $words = $GLOBALS['argv'] ?? [];
    $last = is_array($words) ? end($words) : '';
    $mode = is_string($last) ? $last : '';
    if (!in_array($mode, ['keep', 'delete'], true)) {
        fwrite(STDERR, "Usage: wp eval-file tests/Uninstall/run.php keep|delete\n");
        exit(2);
    }
    $check = static function (bool $condition, string $what): void {
        if (!$condition) {
            fwrite(STDERR, "UNINSTALL FAIL: {$what}\n");
            exit(1);
        }
    };

    $db = Db::fromGlobals();
    $actions = Tables::external('actionscheduler_actions');
    $logs = Tables::external('actionscheduler_logs');
    $like = static fn (string $prefix): string => addcslashes($prefix, '_%\\') . '%';
    $ours = Hooks::name('uninstall/probe');
    $foreignHook = 'another_plugin/uninstall_probe';
    $foreignOption = 'another_plugin_uninstall_probe';
    $administrator = static function () use ($check): WP_Role {
        $role = get_role('administrator');
        $check(null !== $role, 'the administrator role exists');
        if (null === $role) {
            exit(1);
        }

        return $role;
    };

    // What a live site holds, which the job's own activation already put there.
    $check($administrator()->has_cap(Caps::name('manage_bookings')), 'the plugin gave the administrator a capability');
    as_unschedule_all_actions($ours);
    as_unschedule_all_actions($foreignHook);
    as_schedule_single_action(time() + 3600, $ours);
    as_schedule_single_action(time() + 3600, $foreignHook);
    update_option($foreignOption, 'keep me');
    set_transient(Options::key('uninstall_probe'), 1, 3600);
    (new Settings())->save(new DataSettings('delete' === $mode));

    $countOptions = static fn (string $prefix): int => (int) $db->getVar(
        'SELECT COUNT(*) FROM %i WHERE option_name LIKE %s',
        Tables::external('options'),
        $like($prefix)
    );
    $countOurTables = static fn (): int => count($db->getResults('SHOW TABLES LIKE %s', $like(Tables::prefix())));
    $countJobs = static fn (string $hook): int => (int) $db->getVar(
        'SELECT COUNT(*) FROM %i WHERE hook = %s',
        $actions,
        $hook
    );
    $countOrphans = static fn (): int => (int) $db->getVar(
        'SELECT COUNT(*) FROM %i l LEFT JOIN %i a ON a.action_id = l.action_id WHERE a.action_id IS NULL',
        $logs,
        $actions
    );
    $orphansBefore = $countOrphans();
    $tablesBefore = $countOurTables();
    $check($tablesBefore > 10, "the plugin's tables exist before uninstall ({$tablesBefore})");
    $check(1 === $countJobs($ours), 'the probe job is scheduled');

    define('WP_UNINSTALL_PLUGIN', plugin_basename(VAQTYAR_FILE));
    require dirname(VAQTYAR_FILE) . '/uninstall.php';

    // Whatever the owner chose, nothing that is not ours is touched.
    $check('keep me' === get_option($foreignOption), "another plugin's option survives");
    $check(1 === $countJobs($foreignHook), "another plugin's scheduled job survives");
    $check(null !== $db->getVar('SHOW TABLES LIKE %s', Tables::external('posts')), 'WordPress core tables survive');

    if ('keep' === $mode) {
        $check($countOurTables() === $tablesBefore, 'without the opt-in every table stays');
        $check($countOptions(Identity::PREFIX . '_') > 0, 'without the opt-in the options stay');
        $check($administrator()->has_cap(Caps::name('manage_bookings')), 'without the opt-in the capabilities stay');
        $check(1 === $countJobs($ours), 'without the opt-in the scheduled jobs stay');
        echo "UNINSTALL OK keep\n";

        return;
    }

    $check(0 === $countOurTables(), 'with the opt-in every table of the plugin is dropped');
    $check(0 === $countOptions(Identity::PREFIX . '_'), 'with the opt-in every option is deleted');
    $check(0 === $countOptions('_transient_' . Identity::PREFIX . '_'), 'with the opt-in the transients are deleted');
    $check(0 === $countOptions('_transient_timeout_' . Identity::PREFIX . '_'), 'the transient timeouts are deleted');
    $check(0 === $countJobs($ours), 'with the opt-in our scheduled jobs are deleted');
    $check($countOrphans() === $orphansBefore, 'no log of a deleted job is left behind');
    wp_roles()->for_site();
    $left = array_filter(
        array_keys($administrator()->capabilities),
        static fn ($capability): bool => str_starts_with((string) $capability, Identity::PREFIX . '_')
    );
    $check([] === $left, 'with the opt-in no role keeps a capability of the plugin: ' . implode(', ', $left));
    echo "UNINSTALL OK delete\n";
})();
