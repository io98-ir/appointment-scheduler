<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Application;

/**
 * Whether deleting the plugin also deletes its data (the owner's choice;
 * uninstall is the Kernel's Uninstaller).
 */
interface DataPolicy
{
    public function deleteOnUninstall(): bool;

    public function setDeleteOnUninstall(bool $delete): void;
}
