<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

use Vaqtyar\Kernel\Database\Migration;

/**
 * Entry point of a module (src/Modules/<Name>/<Name>Module.php).
 *
 * The kernel calls register() on every module first, then boot() on every
 * module, so boot() may use services that any module registered.
 */
interface Module
{
    /**
     * Stable machine id, e.g. "booking". Unique among active modules.
     */
    public function id(): string;

    /**
     * Binds the module's services. No side effects: no hooks, no queries.
     */
    public function register(Container $container): void;

    /**
     * The module's schema changes, oldest first. Append only: never reorder or
     * remove one, since the stored version is a position in this list.
     * Called on activation and before boot() on every request, so it only
     * constructs objects.
     *
     * @return list<Migration>
     */
    public function migrations(): array;

    /**
     * The module's capabilities (short names, as for Caps::name()) and the
     * roles that get each by default, e.g. ['manage_services' => ['administrator']].
     * Each is given once per role (Capabilities), on activation and on the
     * first request after an update.
     *
     * @return array<string, list<string>>
     */
    public function capabilities(): array;

    /**
     * Hooks the module into WordPress. Scope work to a request through the
     * WordPress hooks themselves (rest_api_init, admin_menu, cli_init, …) and
     * resolve services inside the callbacks, so an unrelated request pays
     * nothing.
     */
    public function boot(Context $context): void;
}
