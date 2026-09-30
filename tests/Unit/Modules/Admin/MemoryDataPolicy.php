<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Admin;

use Vaqtyar\Modules\Admin\Application\DataPolicy;

final class MemoryDataPolicy implements DataPolicy
{
    public bool $delete = false;

    public function deleteOnUninstall(): bool
    {
        return $this->delete;
    }

    public function setDeleteOnUninstall(bool $delete): void
    {
        $this->delete = $delete;
    }
}
