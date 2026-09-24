<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * Explicit-factory service container (ADR-011): no autowiring, ids are class
 * or interface names so that get() is typed. Used only by the kernel and by
 * each module's register()/boot(), never inside Domain or Application code.
 */
final class Container
{
    /** @var array<string, \Closure(self): object> */
    private array $factories = [];

    /** @var array<string, true> */
    private array $shared = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, true> Ids being built right now, in order, to detect cycles. */
    private array $resolving = [];

    /**
     * Registers a factory that runs on every get().
     *
     * @template T of object
     * @param class-string<T> $id
     * @param \Closure(self): T $factory
     */
    public function set(string $id, \Closure $factory): void
    {
        $this->add($id, $factory);
    }

    /**
     * Registers a factory that runs once, on the first get().
     *
     * @template T of object
     * @param class-string<T> $id
     * @param \Closure(self): T $factory
     */
    public function singleton(string $id, \Closure $factory): void
    {
        $this->add($id, $factory);
        $this->shared[$id] = true;
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            /** @var T */
            return $this->instances[$id];
        }
        if (!isset($this->factories[$id])) {
            throw KernelException::serviceNotFound($id);
        }
        if (isset($this->resolving[$id])) {
            throw KernelException::circularDependency([...\array_keys($this->resolving), $id]);
        }

        $this->resolving[$id] = true;
        try {
            $service = ($this->factories[$id])($this);
        } finally {
            unset($this->resolving[$id]);
        }

        if (!$service instanceof $id) {
            throw KernelException::wrongServiceType($id, $service);
        }
        if (isset($this->shared[$id])) {
            $this->instances[$id] = $service;
        }

        return $service;
    }

    /**
     * @param \Closure(self): object $factory
     */
    private function add(string $id, \Closure $factory): void
    {
        if (isset($this->factories[$id])) {
            throw KernelException::serviceAlreadyRegistered($id);
        }
        $this->factories[$id] = $factory;
    }
}
