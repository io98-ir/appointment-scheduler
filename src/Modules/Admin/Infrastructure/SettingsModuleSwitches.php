<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Infrastructure;

use Vaqtyar\Kernel\ModuleCatalog;
use Vaqtyar\Kernel\Settings\ModuleSettings;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Admin\Application\ModuleSwitches;

/**
 * ModuleSwitches on the Kernel's Settings. A change takes effect on the next
 * request, when the plugin picks its modules, so the state is read from the
 * settings, not from this request's catalog.
 */
final class SettingsModuleSwitches implements ModuleSwitches
{
    public function __construct(private readonly ModuleCatalog $catalog, private readonly Settings $settings)
    {
    }

    /**
     * @return list<array{id: string, switchable: bool, enabled: bool}>
     */
    public function entries(): array
    {
        $disabled = $this->settings->get(ModuleSettings::class)->disabled;

        return \array_map(
            static fn (array $entry): array => [
                'id' => $entry['id'],
                'switchable' => $entry['switchable'],
                'enabled' => !$entry['switchable'] || !\in_array($entry['id'], $disabled, true),
            ],
            $this->catalog->entries()
        );
    }

    /**
     * @param list<string> $ids
     */
    public function saveDisabled(array $ids): void
    {
        $this->settings->save(new ModuleSettings($ids));
    }
}
