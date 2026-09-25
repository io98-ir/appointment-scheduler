<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel;

use Vaqtyar\Kernel\Capabilities;
use Vaqtyar\Kernel\Caps;

/**
 * Capabilities on the site's real roles. Roles live in an option and in the
 * WP_Roles object, which the test's rollback does not reset, so the test
 * takes back what it gave.
 */
final class CapabilitiesTest extends \WP_UnitTestCase
{
    private const ROLE = 'booking_tester';

    public function tear_down(): void
    {
        \get_role('administrator')?->remove_cap(Caps::name('test_manage_things'));
        \remove_role(self::ROLE);
        parent::tear_down();
    }

    public function testAnAdministratorGetsTheCapability(): void
    {
        $admin = self::factory()->user->create_and_get(['role' => 'administrator']);
        self::assertInstanceOf(\WP_User::class, $admin);

        (new Capabilities())->grant(['test_manage_things' => ['administrator']]);

        // By id: a WP_User object keeps the capabilities it was loaded with.
        self::assertTrue(\user_can($admin->ID, Caps::name('test_manage_things')));
    }

    public function testACapabilityTakenFromARoleStaysTaken(): void
    {
        $capabilities = new Capabilities();
        $capabilities->grant(['test_manage_things' => ['administrator']]);
        \get_role('administrator')?->remove_cap(Caps::name('test_manage_things'));

        $capabilities->grant(['test_manage_things' => ['administrator']]);

        self::assertFalse(\get_role('administrator')?->has_cap(Caps::name('test_manage_things')));
    }

    public function testARoleAddedLaterGetsTheCapabilityOnTheNextGrant(): void
    {
        $capabilities = new Capabilities();
        $capabilities->grant(['test_manage_things' => [self::ROLE]]);

        \add_role(self::ROLE, 'Booking tester');
        $capabilities->grant(['test_manage_things' => [self::ROLE]]);

        self::assertTrue(\get_role(self::ROLE)?->has_cap(Caps::name('test_manage_things')));
    }
}
