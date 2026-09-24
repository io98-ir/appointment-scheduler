<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * Boots the plugin on plugins_loaded. The main plugin file is the composition
 * root: it passes the module instances, so the kernel depends on no module.
 */
final class Plugin
{
    public function __construct(
        private readonly string $pluginFile,
        private readonly string $version,
    ) {
    }

    /**
     * A module that throws here is a bug and is left to fail loudly: WordPress
     * recovery mode pauses the plugin and tells the site owner.
     */
    public function boot(Module ...$modules): void
    {
        $registry = new ModuleRegistry(...$modules);
        $container = new Container();

        foreach ($registry->all() as $module) {
            $module->register($container);
        }

        $context = new Context($container, $this->pluginFile, $this->version);
        foreach ($registry->all() as $module) {
            $module->boot($context);
        }
    }
}
