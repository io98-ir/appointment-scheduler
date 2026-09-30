<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * Marks a module the site owner may turn off (T6.2). Nothing else depends on
 * a switchable module's services: the rest of the plugin works without it.
 * The core modules (catalog, scheduling, customers, booking, payments, admin)
 * are not switchable.
 */
interface Switchable extends Module
{
}
