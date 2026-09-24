<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Plugin;
use Vaqtyar\Tests\Unit\Kernel\Fixtures\SampleModule;

final class PluginTest extends TestCase
{
    public function testRegistersEveryModuleBeforeBootingAny(): void
    {
        /** @var ArrayObject<int, string> $log */
        $log = new ArrayObject();
        $catalog = new SampleModule('catalog', $log);
        $booking = new SampleModule('booking', $log, bindsService: true);

        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot($catalog, $booking);

        self::assertSame(
            ['catalog:register', 'booking:register', 'catalog:boot', 'booking:boot'],
            $log->getArrayCopy()
        );
        // So boot() may use a service that a later module registered.
        self::assertTrue($catalog->sawServiceAtBoot);
    }

    public function testEveryModuleGetsTheSameContext(): void
    {
        $catalog = new SampleModule('catalog');
        $booking = new SampleModule('booking');

        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot($catalog, $booking);

        self::assertNotNull($catalog->context);
        self::assertSame($catalog->context, $booking->context);
        self::assertSame('/path/to/plugin.php', $catalog->context->pluginFile);
        self::assertSame('1.2.3', $catalog->context->version);
    }

    public function testBootsWithNoModules(): void
    {
        $this->expectNotToPerformAssertions();

        (new Plugin('/path/to/plugin.php', '1.2.3'))->boot();
    }
}
