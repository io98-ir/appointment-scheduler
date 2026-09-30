<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Fixtures;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Switchable;

/**
 * A module the owner may turn off; it does nothing.
 */
final class SwitchableModule implements Switchable
{
    public function __construct(private readonly string $id)
    {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function register(Container $container): void
    {
    }

    /**
     * @return list<\Vaqtyar\Kernel\Database\Migration>
     */
    public function migrations(): array
    {
        return [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function capabilities(): array
    {
        return [];
    }

    public function boot(Context $context): void
    {
    }
}
