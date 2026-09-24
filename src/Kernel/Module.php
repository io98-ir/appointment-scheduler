<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

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
     * Hooks the module into WordPress. Scope work to a request through the
     * WordPress hooks themselves (rest_api_init, admin_menu, cli_init, …) and
     * resolve services inside the callbacks, so an unrelated request pays
     * nothing.
     */
    public function boot(Context $context): void;
}
