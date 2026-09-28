<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Booking\Application\FieldAdminService;
use Vaqtyar\Modules\Booking\Domain\Field\Field;
use Vaqtyar\Modules\Booking\Domain\Field\FieldDefinition;
use Vaqtyar\Modules\Booking\Domain\Field\FieldScope;
use Vaqtyar\Modules\Booking\Domain\Field\FieldType;
use Vaqtyar\Modules\Booking\Domain\Field\ShowIf;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * The admin custom-fields API (docs/api.md):
 *
 *     GET    /fields?scope=&service_id=   the global fields, or one service's own
 *     POST   /fields                      create: 201 with the field
 *     PUT    /fields/{id}                 replace every field: 200 with the field
 *     DELETE /fields/{id}                 204
 *
 * scope is "global" or "service"; service_id is required (and >= 1) only
 * for "service". Each needs the booking capability; FieldAdminService
 * checks it again and rejects a duplicate key or a broken show_if
 * (implementation-notes §4.13).
 */
final class FieldRoutes
{
    private const SCOPES = ['global', 'service'];
    private const MAX_OPTIONS = 50;
    private const ID = ['id' => ['type' => 'integer', 'minimum' => 1, 'required' => true]];

    /**
     * @param \Closure(): FieldAdminService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(FieldAdminService::CAPABILITY));

        $this->router->add(
            '/fields',
            'GET',
            fn (\WP_REST_Request $request): array => \array_map(
                self::toJson(...),
                self::isGlobal($request)
                    ? ($this->service)()->globalFields()
                    : ($this->service)()->forService(self::serviceIdParam($request))
            ),
            $allowed,
            [
                'scope' => ['type' => 'string', 'enum' => self::SCOPES, 'required' => true],
                'service_id' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            ]
        );
        $this->router->add(
            '/fields',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => new \WP_REST_Response(
                self::toJson(($this->service)()->save(self::fromRequest($request, null))),
                201
            ),
            $allowed,
            self::fields()
        );
        $this->router->add(
            '/fields/(?P<id>\d+)',
            'PUT',
            fn (\WP_REST_Request $request): array => self::toJson(
                ($this->service)()->save(self::fromRequest($request, self::id($request)))
            ),
            $allowed,
            self::ID + self::fields()
        );
        $this->router->add(
            '/fields/(?P<id>\d+)',
            'DELETE',
            function (\WP_REST_Request $request): \WP_REST_Response {
                ($this->service)()->delete(self::id($request));

                return new \WP_REST_Response(null, 204);
            },
            $allowed,
            self::ID
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function fields(): array
    {
        return [
            'scope' => ['type' => 'string', 'enum' => self::SCOPES, 'required' => true],
            'service_id' => ['type' => ['integer', 'null'], 'minimum' => 1, 'default' => null],
            'field_key' => ['type' => 'string', 'maxLength' => 64, 'required' => true],
            'type' => [
                'type' => 'string',
                'enum' => \array_map(static fn (FieldType $t): string => $t->value, FieldType::cases()),
                'required' => true,
            ],
            'label' => ['type' => 'string', 'maxLength' => 191, 'required' => true],
            'required' => ['type' => 'boolean', 'default' => false],
            'options' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'maxItems' => self::MAX_OPTIONS,
                'default' => [],
            ],
            'show_if' => [
                'type' => ['object', 'null'],
                'default' => null,
                'properties' => [
                    'field' => ['type' => 'string', 'required' => true],
                    'equals' => ['type' => 'string', 'required' => true],
                ],
            ],
            'sort' => ['type' => 'integer', 'default' => 0],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function toJson(FieldDefinition $definition): array
    {
        $field = $definition->field;

        return [
            'id' => $definition->id,
            'scope' => $definition->scope->value,
            'service_id' => $definition->serviceId,
            'field_key' => $field->key,
            'type' => $field->type->value,
            'label' => $field->label,
            'required' => $field->required,
            'options' => $field->options,
            'show_if' => null === $field->showIf
                ? null
                : ['field' => $field->showIf->fieldKey, 'equals' => $field->showIf->equals],
            'sort' => $field->sort,
        ];
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function fromRequest(\WP_REST_Request $request, ?int $id): FieldDefinition
    {
        $p = $request->get_params();
        $options = $p['options'] ?? [];
        $field = new Field(
            \trim(self::string($p['field_key'] ?? '')),
            FieldType::from(self::string($p['type'] ?? null)),
            \trim(self::string($p['label'] ?? '')),
            (bool) ($p['required'] ?? false),
            \is_array($options) ? \array_values(\array_map(self::string(...), $options)) : [],
            self::showIf($p['show_if'] ?? null),
            self::intValue($p['sort'] ?? 0)
        );
        $serviceId = $p['service_id'] ?? null;

        return new FieldDefinition(
            $id,
            FieldScope::from(self::string($p['scope'] ?? null)),
            null === $serviceId ? null : self::intValue($serviceId),
            $field
        );
    }

    private static function showIf(mixed $raw): ?ShowIf
    {
        if (null === $raw) {
            return null;
        }
        if (!\is_array($raw)) {
            throw self::wrongType();
        }

        return new ShowIf(self::string($raw['field'] ?? null), self::string($raw['equals'] ?? null));
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function isGlobal(\WP_REST_Request $request): bool
    {
        return 'global' === self::string($request->get_param('scope'));
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function serviceIdParam(\WP_REST_Request $request): int
    {
        $serviceId = self::intValue($request->get_param('service_id'));

        return $serviceId >= 1
            ? $serviceId
            : throw new InvalidValue('invalid_field', 'service_id is required, and at least 1, for scope=service.');
    }

    /**
     * From the path only, as CustomerRoutes does: the body may carry an "id".
     *
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function id(\WP_REST_Request $request): int
    {
        $id = $request->get_url_params()['id'] ?? null;

        return \is_numeric($id) ? (int) $id : throw new \LogicException('The route pattern makes the id numeric.');
    }

    /**
     * The schema has checked the type; path parameters arrive as strings.
     */
    private static function intValue(mixed $value): int
    {
        return \is_int($value) || \is_numeric($value) ? (int) $value : throw self::wrongType();
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : throw self::wrongType();
    }

    private static function wrongType(): \LogicException
    {
        return new \LogicException('The schema let a wrong type through.');
    }
}
