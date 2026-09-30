<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Application;

use Vaqtyar\Modules\Admin\Domain\HealthFacts;

/**
 * Where the status screen reads the server and the plugin's own state from.
 */
interface StatusSource
{
    public function facts(): HealthFacts;

    /**
     * @return array{plugin: string, wordpress: string, php: string, database: string}
     */
    public function versions(): array;

    /**
     * How many migrations each owner (a module id, or the kernel) has run.
     *
     * @return array<string, int>
     */
    public function schema(): array;

    /**
     * The newest error lines of the plugin's log (payment and SMS failures
     * among them), newest first.
     *
     * @return list<array{at: string, channel: string, message: string}> `at` is UTC, "Y-m-d H:i:s".
     */
    public function recentErrors(int $limit): array;
}
