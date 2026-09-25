<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Presentation\Rest;

use Vaqtyar\Modules\Catalog\Domain\Color;
use Vaqtyar\Modules\Catalog\Domain\Name;
use Vaqtyar\Modules\Catalog\Domain\ServiceCategory;

/**
 * A service category in the admin API.
 */
final class CategoryJson
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return [
            'name' => Fields::requiredString(),
            'color' => Fields::requiredString(),
            'sort' => Fields::int(0),
        ];
    }

    public function fromInput(Input $in, ?int $id): ServiceCategory
    {
        return new ServiceCategory(
            $id,
            Name::fromInput($in->string('name')),
            Color::fromInput($in->string('color')),
            $in->int('sort', 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(ServiceCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name->value,
            'color' => $category->color->value,
            'sort' => $category->sort,
        ];
    }
}
