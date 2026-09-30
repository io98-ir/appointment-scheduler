<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Admin;

use Vaqtyar\Modules\Admin\Application\ModuleSwitches;

final class MemoryModuleSwitches implements ModuleSwitches
{
    /** @var list<string> */
    public array $disabled = [];

    /**
     * @return list<array{id: string, switchable: bool, enabled: bool}>
     */
    public function entries(): array
    {
        $entries = [['id' => 'booking', 'switchable' => false]];
        foreach (['widget', 'notifications'] as $id) {
            $entries[] = ['id' => $id, 'switchable' => true];
        }

        return \array_map(
            fn (array $entry): array => $entry + ['enabled' => !\in_array($entry['id'], $this->disabled, true)],
            $entries
        );
    }

    /**
     * @param list<string> $ids
     */
    public function saveDisabled(array $ids): void
    {
        $this->disabled = $ids;
    }
}
