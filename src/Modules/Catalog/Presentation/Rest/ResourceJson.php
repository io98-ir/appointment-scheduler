<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Presentation\Rest;

use Vaqtyar\Modules\Catalog\Domain\BookableResource;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\Slug;

/**
 * A resource (room, chair, device) in the admin API.
 */
final class ResourceJson
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return [
            'name' => Fields::requiredString(),
            'group_key' => Fields::requiredString(),
            'location_id' => Fields::optionalId(),
            'capacity' => Fields::int(1),
            'status' => Fields::status(),
        ];
    }

    public function fromInput(Input $in, ?int $id): BookableResource
    {
        return new BookableResource(
            $id,
            Name::fromInput($in->string('name')),
            Slug::fromInput($in->string('group_key')),
            $in->intOrNull('location_id'),
            $in->int('capacity', 1),
            Status::from($in->string('status', Status::Active->value)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(BookableResource $resource): array
    {
        return [
            'id' => $resource->id,
            'name' => $resource->name->value,
            'group_key' => $resource->groupKey->value,
            'location_id' => $resource->locationId,
            'capacity' => $resource->capacity,
            'status' => $resource->status->value,
        ];
    }
}
