<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Fixtures;

use ArrayObject;
use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Module;

/**
 * A module that records what the kernel does with it.
 */
final class SampleModule implements Module
{
    public ?Context $context = null;
    public bool $sawServiceAtBoot = false;

    /**
     * @param ArrayObject<int, string>|null $log Shared across modules to check the overall order.
     */
    public function __construct(
        private readonly string $id,
        private readonly ?ArrayObject $log = null,
        private readonly bool $bindsService = false,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function register(Container $container): void
    {
        $this->log?->append($this->id . ':register');
        if ($this->bindsService) {
            $container->singleton(SampleService::class, static fn (): SampleService => new SampleService());
        }
    }

    public function boot(Context $context): void
    {
        $this->log?->append($this->id . ':boot');
        $this->context = $context;
        $this->sawServiceAtBoot = $context->container->has(SampleService::class);
    }
}
