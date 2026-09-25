<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Presentation\Rest;

use Vaqtyar\Modules\Catalog\Domain\Name;
use Vaqtyar\Modules\Catalog\Domain\ResourceRequirement;
use Vaqtyar\Modules\Catalog\Domain\Service;
use Vaqtyar\Modules\Catalog\Domain\ServiceStaff;
use Vaqtyar\Modules\Catalog\Domain\Slug;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Modules\Catalog\Domain\Variant;

/**
 * A service with its variants, staff assignments and resource requirements,
 * sent and saved whole. A variant with an id keeps that stored variant; one
 * without is new; a stored one left out is deleted.
 */
final class ServiceJson
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        $optionalInt = ['type' => ['integer', 'null']];

        return [
            'name' => Fields::requiredString(),
            'category_id' => Fields::optionalId(),
            'description' => Fields::text(),
            'image_id' => Fields::optionalId(),
            'capacity' => Fields::int(1),
            'status' => Fields::status(),
            'sort' => Fields::int(0),
            'variants' => Fields::objects([
                'id' => $optionalInt,
                'label' => ['type' => 'string'],
                'duration_min' => ['type' => 'integer', 'required' => true],
                'price' => Fields::money(),
                'is_default' => ['type' => 'boolean', 'required' => true],
                'buffer_before_min' => ['type' => 'integer'],
                'buffer_after_min' => ['type' => 'integer'],
                'slot_step_min' => $optionalInt,
                'sort' => ['type' => 'integer'],
            ], required: true),
            'staff' => Fields::objects([
                'staff_id' => ['type' => 'integer', 'required' => true],
                'variant_id' => $optionalInt,
                'price' => Fields::money(nullable: true),
                'duration_min' => $optionalInt,
            ]),
            'resources' => Fields::objects([
                'group_key' => ['type' => 'string', 'required' => true],
                'quantity' => ['type' => 'integer'],
            ]),
        ];
    }

    public function fromInput(Input $in, ?int $id): Service
    {
        return new Service(
            $id,
            Name::fromInput($in->string('name')),
            \array_map(self::variant(...), $in->objects('variants')),
            \array_map(
                static fn (Input $a): ServiceStaff => new ServiceStaff(
                    $a->int('staff_id'),
                    $a->intOrNull('variant_id'),
                    $a->moneyOrNull('price'),
                    $a->intOrNull('duration_min'),
                ),
                $in->objects('staff')
            ),
            \array_map(
                static fn (Input $r): ResourceRequirement => new ResourceRequirement(
                    Slug::fromInput($r->string('group_key')),
                    $r->int('quantity', 1),
                ),
                $in->objects('resources')
            ),
            $in->intOrNull('category_id'),
            $in->string('description', ''),
            $in->wpIdOrNull('image_id', \wp_attachment_is_image(...), 'invalid_image'),
            $in->int('capacity', 1),
            Status::from($in->string('status', Status::Active->value)),
            $in->int('sort', 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name->value,
            'category_id' => $service->categoryId,
            'description' => $service->description,
            'image_id' => $service->imageId,
            'capacity' => $service->capacity,
            'status' => $service->status->value,
            'sort' => $service->sort,
            'variants' => \array_map(static fn (Variant $v): array => [
                'id' => $v->id,
                'label' => $v->label,
                'duration_min' => $v->durationMin,
                'price' => $v->price->toArray(),
                'is_default' => $v->isDefault,
                'buffer_before_min' => $v->bufferBeforeMin,
                'buffer_after_min' => $v->bufferAfterMin,
                'slot_step_min' => $v->slotStepMin,
                'sort' => $v->sort,
            ], $service->variants),
            'staff' => \array_map(static fn (ServiceStaff $a): array => [
                'staff_id' => $a->staffId,
                'variant_id' => $a->variantId,
                'price' => $a->price?->toArray(),
                'duration_min' => $a->durationMin,
            ], $service->staff),
            'resources' => \array_map(static fn (ResourceRequirement $r): array => [
                'group_key' => $r->groupKey->value,
                'quantity' => $r->quantity,
            ], $service->resources),
        ];
    }

    private static function variant(Input $v): Variant
    {
        return new Variant(
            $v->intOrNull('id'),
            $v->string('label', ''),
            $v->int('duration_min'),
            $v->money('price'),
            $v->bool('is_default'),
            $v->int('buffer_before_min', 0),
            $v->int('buffer_after_min', 0),
            $v->intOrNull('slot_step_min'),
            $v->int('sort', 0),
        );
    }
}
