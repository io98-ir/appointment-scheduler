<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * What a module gets at boot().
 */
final class Context
{
    public function __construct(
        public readonly Container $container,
        /**
         * Absolute path of the main plugin file, for plugins_url() and plugin_basename().
         * Not for register_activation_hook(): boot() does not run on the activation request.
         */
        public readonly string $pluginFile,
        public readonly string $version,
    ) {
    }
}
