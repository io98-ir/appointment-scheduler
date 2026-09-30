<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * Every module the plugin ships and which of them the owner turned off.
 * Unlike the ModuleRegistry it still knows a disabled module, so the status
 * screen can offer to turn it on again.
 */
final class ModuleCatalog
{
    /** @var list<Module> */
    private readonly array $all;

    /** @var list<string> */
    private readonly array $disabled;

    /**
     * @param array<int|string, Module> $all
     * @param list<string> $disabled Ids; a core module's id, or an unknown one, is ignored.
     */
    public function __construct(array $all, array $disabled)
    {
        $this->all = \array_values($all);
        $this->disabled = \array_values(\array_filter(
            $disabled,
            fn (string $id): bool => \in_array($id, $this->switchableIds(), true)
        ));
    }

    /**
     * The modules that boot, in registration order.
     *
     * @return list<Module>
     */
    public function active(): array
    {
        return \array_values(\array_filter(
            $this->all,
            fn (Module $module): bool => !\in_array($module->id(), $this->disabled, true)
        ));
    }

    /**
     * @return list<array{id: string, switchable: bool, enabled: bool}>
     */
    public function entries(): array
    {
        return \array_map(
            fn (Module $module): array => [
                'id' => $module->id(),
                'switchable' => $module instanceof Switchable,
                'enabled' => !\in_array($module->id(), $this->disabled, true),
            ],
            $this->all
        );
    }

    /**
     * @return list<string>
     */
    public function switchableIds(): array
    {
        $ids = [];
        foreach ($this->all as $module) {
            if ($module instanceof Switchable) {
                $ids[] = $module->id();
            }
        }

        return $ids;
    }
}
