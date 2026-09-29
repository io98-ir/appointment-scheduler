<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

use Vaqtyar\Modules\Notifications\Domain\Template;
use Vaqtyar\Modules\Notifications\Domain\Trigger;

/**
 * The stored templates.
 */
interface TemplateRepository
{
    public function find(int $id): ?Template;

    /**
     * The enabled ones for a trigger, by id.
     *
     * @return list<Template>
     */
    public function enabledFor(Trigger $trigger): array;

    /**
     * @return list<Template> all, by id.
     */
    public function all(): array;

    public function save(Template $template): Template;

    public function delete(int $id): void;
}
