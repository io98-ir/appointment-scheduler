<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * The active modules, in the order they are registered and booted.
 */
final class ModuleRegistry
{
    /** @var array<string, Module> */
    private array $modules = [];

    public function __construct(Module ...$modules)
    {
        foreach ($modules as $module) {
            $this->add($module);
        }
    }

    public function add(Module $module): void
    {
        $id = $module->id();
        if (isset($this->modules[$id])) {
            throw KernelException::moduleAlreadyRegistered($id);
        }
        $this->modules[$id] = $module;
    }

    /**
     * @return list<Module>
     */
    public function all(): array
    {
        return \array_values($this->modules);
    }
}
