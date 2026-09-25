<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Fixtures;

use ArrayObject;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Kernel\Database\Migration;

/**
 * A migration that only records that it ran, or fails like a bad ALTER.
 */
final class RecordingMigration implements Migration
{
    /**
     * @param ArrayObject<int, string> $ran Shared across migrations to check the overall order.
     */
    public function __construct(
        private readonly string $name,
        private readonly ArrayObject $ran,
        private readonly bool $fails = false,
    ) {
    }

    public function up(Db $db): void
    {
        $this->ran->append($this->name);
        if ($this->fails) {
            throw DbException::queryFailed("Duplicate column name 'x'", 1060);
        }
    }
}
