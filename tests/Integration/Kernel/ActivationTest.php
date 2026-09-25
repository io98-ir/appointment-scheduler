<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel;

/**
 * The activation hook lives at the top level of the main file, since boot()
 * does not run on the activation request (implementation-notes §1).
 */
final class ActivationTest extends \WP_UnitTestCase
{
    public function testTheMainFileRegistersTheActivationHook(): void
    {
        $hook = 'activate_' . \plugin_basename(\VAQTYAR_FILE);

        self::assertNotFalse(\has_action($hook));

        // Runs the same code as a real activation; it must not fail.
        \do_action($hook, false);
    }
}
