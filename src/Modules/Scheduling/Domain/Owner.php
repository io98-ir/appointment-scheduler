<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * A staff member, resource or location by its catalog id.
 */
final class Owner
{
    public function __construct(public readonly OwnerType $type, public readonly int $id)
    {
        if ($id < 1) {
            throw new InvalidValue('invalid_id', 'An id is a positive integer.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
