<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Infrastructure;

use Vaqtyar\Kernel\Settings\DataSettings;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Admin\Application\DataPolicy;

/**
 * DataPolicy on the Kernel's Settings, the group the Uninstaller reads.
 */
final class SettingsDataPolicy implements DataPolicy
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function deleteOnUninstall(): bool
    {
        return $this->settings->get(DataSettings::class)->deleteOnUninstall;
    }

    public function setDeleteOnUninstall(bool $delete): void
    {
        $this->settings->save(new DataSettings($delete));
    }
}
