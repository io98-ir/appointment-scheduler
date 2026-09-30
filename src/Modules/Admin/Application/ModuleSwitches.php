<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Application;

/**
 * The modules and which of them the owner turned off.
 */
interface ModuleSwitches
{
    /**
     * @return list<array{id: string, switchable: bool, enabled: bool}>
     */
    public function entries(): array;

    /**
     * @param list<string> $ids Every switchable module that stays off; the rest are on.
     */
    public function saveDisabled(array $ids): void;
}
