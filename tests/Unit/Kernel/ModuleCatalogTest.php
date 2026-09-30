<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\ModuleCatalog;
use Vaqtyar\Kernel\Settings\ModuleSettings;
use Vaqtyar\Tests\Unit\Kernel\Fixtures\SampleModule;
use Vaqtyar\Tests\Unit\Kernel\Fixtures\SwitchableModule;

final class ModuleCatalogTest extends TestCase
{
    public function testEveryModuleBootsWhenNothingIsDisabled(): void
    {
        $modules = [new SampleModule('booking'), new SwitchableModule('widget')];

        self::assertSame($modules, (new ModuleCatalog($modules, []))->active());
    }

    public function testADisabledSwitchableModuleDoesNotBoot(): void
    {
        $core = new SampleModule('booking');
        $catalog = new ModuleCatalog([$core, new SwitchableModule('widget')], ['widget']);

        self::assertSame([$core], $catalog->active());
        self::assertSame(
            [
                ['id' => 'booking', 'switchable' => false, 'enabled' => true],
                ['id' => 'widget', 'switchable' => true, 'enabled' => false],
            ],
            $catalog->entries()
        );
    }

    public function testACoreModuleCannotBeDisabledThroughTheSettings(): void
    {
        $core = new SampleModule('booking');
        $catalog = new ModuleCatalog([$core], ['booking', 'unknown']);

        self::assertSame([$core], $catalog->active());
        self::assertTrue($catalog->entries()[0]['enabled']);
    }

    public function testListsTheSwitchableIds(): void
    {
        $catalog = new ModuleCatalog(
            [new SampleModule('booking'), new SwitchableModule('widget'), new SwitchableModule('notifications')],
            []
        );

        self::assertSame(['widget', 'notifications'], $catalog->switchableIds());
    }

    public function testSettingsKeepOnlyValidUniqueIds(): void
    {
        $settings = ModuleSettings::fromStored(['disabled' => ['widget', 'widget', 5, 'Bad Id', 'notifications']]);

        self::assertSame(['widget', 'notifications'], $settings->disabled);
        self::assertSame([], ModuleSettings::fromStored(['disabled' => 'widget'])->disabled);
        self::assertSame(['disabled' => ['widget']], (new ModuleSettings(['widget']))->toStored());
    }
}
