<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Presentation\Rest;

use Vaqtyar\Modules\Catalog\Domain\Extra;
use Vaqtyar\Modules\Catalog\Domain\Name;
use Vaqtyar\Modules\Catalog\Domain\Status;

/**
 * An extra in the admin API.
 */
final class ExtraJson
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return [
            'name' => Fields::requiredString(),
            'price' => Fields::money(),
            'duration_min' => Fields::requiredInt(),
            'service_id' => Fields::optionalId(),
            'max_qty' => Fields::int(1),
            'status' => Fields::status(),
        ];
    }

    public function fromInput(Input $in, ?int $id): Extra
    {
        return new Extra(
            $id,
            Name::fromInput($in->string('name')),
            $in->money('price'),
            $in->int('duration_min'),
            $in->intOrNull('service_id'),
            $in->int('max_qty', 1),
            Status::from($in->string('status', Status::Active->value)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(Extra $extra): array
    {
        return [
            'id' => $extra->id,
            'name' => $extra->name->value,
            'price' => $extra->price->toArray(),
            'duration_min' => $extra->durationMin,
            'service_id' => $extra->serviceId,
            'max_qty' => $extra->maxQty,
            'status' => $extra->status->value,
        ];
    }
}
