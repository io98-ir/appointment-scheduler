<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * Gives the modules' capabilities to their default roles (Module::capabilities()).
 *
 * Each capability goes to each role once: the pairs already given are kept
 * in an autoloaded option, so a capability the site owner later takes from a
 * role stays taken, and a request with nothing new sends no query. A role
 * that does not exist yet is skipped and gets the capability on the first
 * request after it appears.
 *
 * Roles are per site, and so is the option: on a multisite each site catches
 * up on its own first request, like the migrations.
 */
final class Capabilities
{
    /**
     * @param array<string, list<string>> $capabilities Capability (short name,
     *     as for Caps::name()) => the roles that get it by default.
     */
    public function grant(array $capabilities): void
    {
        if ([] === $capabilities) {
            return;
        }

        $key = Options::key('granted_caps');
        $stored = \get_option($key, []);
        $granted = \is_array($stored) ? \array_fill_keys(\array_filter($stored, 'is_string'), true) : [];
        $changed = false;

        foreach ($capabilities as $capability => $roles) {
            $name = Caps::name($capability);
            foreach ($roles as $roleName) {
                $pair = "{$name} {$roleName}";
                if (isset($granted[$pair])) {
                    continue;
                }
                $role = \get_role($roleName);
                if (null === $role) {
                    continue;
                }
                $role->add_cap($name);
                $granted[$pair] = true;
                $changed = true;
            }
        }

        if ($changed) {
            \update_option($key, \array_keys($granted), true);
        }
    }
}
