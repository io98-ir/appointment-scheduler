<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use SplQueue;
use SplStack;
use stdClass;
use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\KernelException;

final class ContainerTest extends TestCase
{
    public function testSingletonReturnsTheSameInstanceAndBuildsItOnce(): void
    {
        $container = new Container();
        $calls = 0;
        $container->singleton(stdClass::class, static function () use (&$calls): stdClass {
            ++$calls;

            return new stdClass();
        });

        self::assertSame(0, $calls, 'The factory must not run before the first get().');
        self::assertSame($container->get(stdClass::class), $container->get(stdClass::class));
        self::assertSame(1, $calls);
    }

    public function testSetReturnsANewInstanceOnEveryGet(): void
    {
        $container = new Container();
        $container->set(stdClass::class, static fn (): stdClass => new stdClass());

        self::assertNotSame($container->get(stdClass::class), $container->get(stdClass::class));
    }

    public function testFactoryReceivesTheContainerToResolveDependencies(): void
    {
        $container = new Container();
        $container->singleton(SplQueue::class, static fn (): SplQueue => new SplQueue());
        $container->singleton(
            ArrayObject::class,
            static fn (Container $c): ArrayObject => new ArrayObject([$c->get(SplQueue::class)])
        );

        self::assertSame($container->get(SplQueue::class), $container->get(ArrayObject::class)[0]);
    }

    public function testHasIsTrueOnlyForRegisteredIds(): void
    {
        $container = new Container();
        $container->set(stdClass::class, static fn (): stdClass => new stdClass());

        self::assertTrue($container->has(stdClass::class));
        self::assertFalse($container->has(SplQueue::class));
    }

    public function testUnknownIdFails(): void
    {
        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('No service is registered for "stdClass".');

        (new Container())->get(stdClass::class);
    }

    public function testRegisteringAnIdTwiceFails(): void
    {
        $container = new Container();
        $container->set(stdClass::class, static fn (): stdClass => new stdClass());

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('A service is already registered for "stdClass".');

        $container->singleton(stdClass::class, static fn (): stdClass => new stdClass());
    }

    public function testFactoryReturningTheWrongTypeFails(): void
    {
        $container = new Container();
        // PHPStan infers T from both arguments, so it cannot catch this; the runtime check does.
        $container->set(SplQueue::class, static fn (): object => new SplStack());

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('The factory for "SplQueue" returned SplStack.');

        $container->get(SplQueue::class);
    }

    public function testCircularDependencyFailsInsteadOfRecursingForever(): void
    {
        $container = new Container();
        $container->singleton(SplQueue::class, static function (Container $c): SplQueue {
            $c->get(SplStack::class);

            return new SplQueue();
        });
        $container->singleton(SplStack::class, static function (Container $c): SplStack {
            $c->get(SplQueue::class);

            return new SplStack();
        });

        $this->expectException(KernelException::class);
        $this->expectExceptionMessage('Circular dependency: SplQueue -> SplStack -> SplQueue.');

        $container->get(SplQueue::class);
    }

    public function testAFailedFactoryDoesNotLeaveTheIdMarkedAsResolving(): void
    {
        $container = new Container();
        $attempts = new SplQueue();
        $container->singleton(stdClass::class, static function () use ($attempts): stdClass {
            $attempts->enqueue('attempt');
            if (1 === $attempts->count()) {
                throw new \RuntimeException('boom');
            }

            return new stdClass();
        });

        try {
            $container->get(stdClass::class);
            self::fail('The factory should have thrown.');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        // Not reported as a circular dependency: the id was released by the failure.
        self::assertInstanceOf(stdClass::class, $container->get(stdClass::class));
    }
}
