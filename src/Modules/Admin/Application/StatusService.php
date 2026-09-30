<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Application;

use Vaqtyar\Modules\Admin\Domain\HealthCheck;
use Vaqtyar\Modules\Admin\Domain\HealthEvaluator;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * The System Status screen (T6.2): versions, the health checks, the queue,
 * recent errors, and which optional modules are on. Each call checks the
 * capability again after the REST permission callback (architecture §12).
 */
final class StatusService
{
    /** Opens the status screen and turns modules on or off (short name, Caps::name()). */
    public const CAPABILITY = 'manage_system';

    private const RECENT_ERRORS = 10;

    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly StatusSource $source,
        private readonly ModuleSwitches $modules,
        private readonly DataPolicy $data,
        private readonly HealthEvaluator $evaluator = new HealthEvaluator(),
    ) {
    }

    /**
     * @return array{
     *     versions: array{plugin: string, wordpress: string, php: string, database: string},
     *     checks: list<HealthCheck>,
     *     queue: array{pending: int, late: int, failed: int},
     *     schema: array<string, int>,
     *     modules: list<array{id: string, switchable: bool, enabled: bool}>,
     *     delete_on_uninstall: bool,
     *     errors: list<array{at: string, channel: string, message: string}>
     * }
     */
    public function report(): array
    {
        $this->authorize();
        $facts = $this->source->facts();

        return [
            'versions' => $this->source->versions(),
            'checks' => $this->evaluator->evaluate($facts),
            'queue' => [
                'pending' => $facts->queuePending,
                'late' => $facts->queueLate,
                'failed' => $facts->queueFailed,
            ],
            'schema' => $this->source->schema(),
            'modules' => $this->modules->entries(),
            'delete_on_uninstall' => $this->data->deleteOnUninstall(),
            'errors' => $this->source->recentErrors(self::RECENT_ERRORS),
        ];
    }

    /**
     * Whether deleting the plugin from WordPress also deletes every booking,
     * customer and setting. Off until the owner turns it on.
     */
    public function setDeleteOnUninstall(bool $delete): bool
    {
        $this->authorize();
        $this->data->setDeleteOnUninstall($delete);

        return $this->data->deleteOnUninstall();
    }

    /**
     * @return list<array{id: string, switchable: bool, enabled: bool}> The modules after the change.
     * @throws InvalidValue module_not_switchable: unknown, or a core module.
     */
    public function setModuleEnabled(string $id, bool $enabled): array
    {
        $this->authorize();
        $disabled = [];
        $found = false;
        foreach ($this->modules->entries() as $entry) {
            if ($entry['id'] === $id) {
                if (!$entry['switchable']) {
                    throw new InvalidValue('module_not_switchable', 'This module cannot be turned off.');
                }
                $found = true;
            }
            $off = $entry['id'] === $id ? !$enabled : !$entry['enabled'];
            if ($off) {
                $disabled[] = $entry['id'];
            }
        }
        if (!$found) {
            throw new InvalidValue('module_not_switchable', 'This module cannot be turned off.');
        }
        $this->modules->saveDisabled($disabled);

        return $this->modules->entries();
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }
}
