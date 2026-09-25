<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\KernelException;
use Vaqtyar\Kernel\ModuleRegistry;
use Vaqtyar\Tests\Unit\Kernel\Fixtures\SampleModule;

final class ModuleRegistryTest extends TestCase
{
    public function testKeepsModulesInRegistrationOrder(): void
    {
        $a = new SampleModule('catalog');
        $b = new SampleModule('booking');
        $registry = new ModuleRegistry($a);
        $registry->add($b);

        self::assertSame([$a, $b], $registry->all());
    }

    public function testEmptyRegistryListsNoModules(): void
    {
        self::assertSame([], (new ModuleRegistry())->all());
    }

    public function testDuplicateIdFails(): void
    {
        $registry = new ModuleRegistry(new SampleModule('catalog'));

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('A module with the id "catalog" is already registered.');

        $registry->add(new SampleModule('catalog'));
    }

    public function testTheKernelIdIsReserved(): void
    {
        // The kernel records its own schema version under this id (Migrator).
        $this->expectException(KernelException::class);

        new ModuleRegistry(new SampleModule('kernel'));
    }
}
