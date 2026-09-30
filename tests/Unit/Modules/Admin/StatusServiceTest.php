<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Admin;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Admin\Application\DataPolicy;
use Vaqtyar\Modules\Admin\Application\ModuleSwitches;
use Vaqtyar\Modules\Admin\Application\StatusService;
use Vaqtyar\Modules\Admin\Application\StatusSource;
use Vaqtyar\Modules\Admin\Domain\HealthFacts;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;

final class StatusServiceTest extends TestCase
{
    public function testReportsTheServerAndTheQueue(): void
    {
        $report = $this->service(new MemoryModuleSwitches(), true)->report();

        self::assertSame('8.3.0', $report['versions']['php']);
        self::assertSame(['pending' => 4, 'late' => 1, 'failed' => 2], $report['queue']);
        self::assertSame(['kernel' => 2, 'booking' => 5], $report['schema']);
        self::assertSame('payments', $report['errors'][0]['channel']);
        self::assertSame('queue', $report['checks'][5]->id);
    }

    public function testTurnsASwitchableModuleOffAndOn(): void
    {
        $switches = new MemoryModuleSwitches();
        $service = $this->service($switches, true);

        $off = $service->setModuleEnabled('widget', false);
        self::assertSame(['widget'], $switches->disabled);
        self::assertFalse($off[1]['enabled']);

        $service->setModuleEnabled('notifications', false);
        self::assertSame(['widget', 'notifications'], $switches->disabled);

        $service->setModuleEnabled('widget', true);
        self::assertSame(['notifications'], $switches->disabled);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'a core module' => ['booking'];
        yield 'an unknown module' => ['nothing'];
    }

    /**
     * @dataProvider refused
     */
    public function testRefusesAModuleThatCannotBeTurnedOff(string $id): void
    {
        $switches = new MemoryModuleSwitches();

        try {
            $this->service($switches, true)->setModuleEnabled($id, false);
            self::fail('Expected InvalidValue.');
        } catch (InvalidValue $e) {
            self::assertSame('module_not_switchable', $e->errorCode);
        }
        self::assertSame([], $switches->disabled);
    }

    public function testDeletingDataOnUninstallIsOffUntilTheOwnerTurnsItOn(): void
    {
        $data = new MemoryDataPolicy();
        $service = $this->service(new MemoryModuleSwitches(), true, $data);

        self::assertFalse($service->report()['delete_on_uninstall']);
        self::assertTrue($service->setDeleteOnUninstall(true));
        self::assertTrue($data->delete);
        self::assertTrue($service->report()['delete_on_uninstall']);
        self::assertFalse($service->setDeleteOnUninstall(false));
    }

    public function testEveryCallChecksTheCapability(): void
    {
        $switches = new MemoryModuleSwitches();
        $data = new MemoryDataPolicy();
        $service = $this->service($switches, false, $data);

        $calls = [
            static fn () => $service->report(),
            static fn () => $service->setModuleEnabled('widget', false),
            static fn () => $service->setDeleteOnUninstall(true),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                self::fail('Expected Forbidden.');
            } catch (Forbidden) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame([], $switches->disabled);
        self::assertFalse($data->delete);
    }

    private function service(ModuleSwitches $switches, bool $allowed, ?DataPolicy $data = null): StatusService
    {
        $source = new class implements StatusSource {
            public function facts(): HealthFacts
            {
                return new HealthFacts(true, true, true, 12600, 12600, true, 4, 1, 2);
            }

            /**
             * @return array{plugin: string, wordpress: string, php: string, database: string}
             */
            public function versions(): array
            {
                return ['plugin' => '1.0.0', 'wordpress' => '6.6', 'php' => '8.3.0', 'database' => '8.0'];
            }

            /**
             * @return array<string, int>
             */
            public function schema(): array
            {
                return ['kernel' => 2, 'booking' => 5];
            }

            /**
             * @return list<array{at: string, channel: string, message: string}>
             */
            public function recentErrors(int $limit): array
            {
                return [['at' => '2026-09-30 10:00:00', 'channel' => 'payments', 'message' => 'Verify failed']];
            }
        };
        $authorizer = new class ($allowed) implements Authorizer {
            public function __construct(private readonly bool $allowed)
            {
            }

            public function allows(string $capability): bool
            {
                return $this->allowed && 'manage_system' === $capability;
            }
        };

        return new StatusService($authorizer, $source, $switches, $data ?? new MemoryDataPolicy());
    }
}
