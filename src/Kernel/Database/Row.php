<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Database;

/**
 * One row as MySQL returns it (every value a string, NULL as null), read
 * back into PHP types. A missing column is a bug in the query.
 */
final class Row
{
    /**
     * @param array<string, string|null> $values
     */
    public function __construct(private readonly array $values)
    {
    }

    public function int(string $column): int
    {
        return (int) $this->value($column, false);
    }

    public function intOrNull(string $column): ?int
    {
        $value = $this->value($column, true);

        return null === $value ? null : (int) $value;
    }

    public function string(string $column): string
    {
        return (string) $this->value($column, false);
    }

    public function stringOrNull(string $column): ?string
    {
        return $this->value($column, true);
    }

    private function value(string $column, bool $nullable): ?string
    {
        if (!\array_key_exists($column, $this->values)) {
            throw new \LogicException("The query did not select the column {$column}.");
        }
        $value = $this->values[$column];
        if (null === $value && !$nullable) {
            throw new \LogicException("The column {$column} is NULL.");
        }

        return $value;
    }
}
