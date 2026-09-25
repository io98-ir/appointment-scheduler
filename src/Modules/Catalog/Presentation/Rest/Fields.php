<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Presentation\Rest;

use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Shared\Domain\Currency;

/**
 * Schema pieces the catalog resources share. The schema checks types (400
 * rest_invalid_param); the domain checks the rules (422 with its code).
 * A field left out of a PUT takes its default: PUT replaces the whole item.
 */
final class Fields
{
    /**
     * @return array<string, mixed>
     */
    public static function requiredString(): array
    {
        return ['type' => 'string', 'required' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public static function text(): array
    {
        return ['type' => 'string', 'default' => ''];
    }

    /**
     * An optional value such as a phone number; "" reads as none.
     *
     * @return array<string, mixed>
     */
    public static function optionalString(): array
    {
        return ['type' => ['string', 'null'], 'default' => null];
    }

    /**
     * @return array<string, mixed>
     */
    public static function optionalId(): array
    {
        return ['type' => ['integer', 'null'], 'default' => null];
    }

    /**
     * @return array<string, mixed>
     */
    public static function int(int $default): array
    {
        return ['type' => 'integer', 'default' => $default];
    }

    /**
     * @return array<string, mixed>
     */
    public static function requiredInt(): array
    {
        return ['type' => 'integer', 'required' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public static function status(): array
    {
        return [
            'type' => 'string',
            'enum' => \array_map(static fn (Status $status): string => $status->value, Status::cases()),
            'default' => Status::Active->value,
        ];
    }

    /**
     * {"amount": int, "currency": "IRR"} (architecture §9).
     *
     * @return array<string, mixed>
     */
    public static function money(bool $nullable = false): array
    {
        return [
            'type' => $nullable ? ['object', 'null'] : 'object',
            'properties' => [
                'amount' => ['type' => 'integer', 'required' => true],
                'currency' => [
                    'type' => 'string',
                    'enum' => \array_map(static fn (Currency $c): string => $c->value, Currency::cases()),
                    'required' => true,
                ],
            ],
            'additionalProperties' => false,
        ] + ($nullable ? ['default' => null] : ['required' => true]);
    }

    /**
     * A list of objects with these properties: optional and empty by
     * default, or required with at least one.
     *
     * @param array<string, array<string, mixed>> $properties
     * @return array<string, mixed>
     */
    public static function objects(array $properties, bool $required = false): array
    {
        $list = [
            'type' => 'array',
            'items' => ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false],
        ];

        // No default on a required list: WordPress validates defaults too
        // (sanitize_params), and [] would fail minItems on every request.
        return $required ? $list + ['required' => true, 'minItems' => 1] : $list + ['default' => []];
    }
}
