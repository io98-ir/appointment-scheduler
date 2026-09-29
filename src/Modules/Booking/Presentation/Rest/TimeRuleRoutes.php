<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Booking\Application\TimeRuleAdminService;
use Vaqtyar\Modules\Booking\Domain\Pricing\TimeRule;
use Vaqtyar\Modules\Booking\Domain\Pricing\TimeRuleDefinition;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;

/**
 * The admin time-rules API (docs/api.md), the "time" rows of price_rules:
 *
 *     GET    /time-rules        every rule, highest priority first
 *     POST   /time-rules        create: 201 with the rule
 *     PUT    /time-rules/{id}   replace every field: 200 with the rule
 *     DELETE /time-rules/{id}   204
 *
 * Each needs the booking capability; TimeRuleAdminService checks it again.
 * from and to are local "HH:MM" ("24:00" ends the day); valid_from and
 * valid_to are local "YYYY-MM-DD" dates, both inclusive.
 */
final class TimeRuleRoutes
{
    private const ID = ['id' => ['type' => 'integer', 'minimum' => 1, 'required' => true]];
    private const TIME = '^\d{2}:\d{2}$';
    private const DATE = '^\d{4}-\d{2}-\d{2}$';

    /**
     * @param \Closure(): TimeRuleAdminService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(TimeRuleAdminService::CAPABILITY));

        $this->router->add(
            '/time-rules',
            'GET',
            fn (): array => \array_map(self::toJson(...), ($this->service)()->all()),
            $allowed
        );
        $this->router->add(
            '/time-rules',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => new \WP_REST_Response(
                self::toJson(($this->service)()->save(self::fromRequest($request, 0))),
                201
            ),
            $allowed,
            self::fields()
        );
        $this->router->add(
            '/time-rules/(?P<id>\d+)',
            'PUT',
            fn (\WP_REST_Request $request): array => self::toJson(
                ($this->service)()->save(self::fromRequest($request, self::id($request)))
            ),
            $allowed,
            self::ID + self::fields()
        );
        $this->router->add(
            '/time-rules/(?P<id>\d+)',
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
            'service_id' => ['type' => ['integer', 'null'], 'minimum' => 1, 'default' => null],
            'priority' => ['type' => 'integer', 'default' => 0],
            'active' => ['type' => 'boolean', 'default' => true],
            'weekdays' => [
                'type' => 'array',
                'items' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 6],
                'maxItems' => 7,
                'default' => [],
            ],
            'from' => ['type' => 'string', 'pattern' => self::TIME, 'required' => true],
            'to' => ['type' => 'string', 'pattern' => self::TIME, 'required' => true],
            'valid_from' => ['type' => ['string', 'null'], 'pattern' => self::DATE, 'default' => null],
            'valid_to' => ['type' => ['string', 'null'], 'pattern' => self::DATE, 'default' => null],
            'percent' => ['type' => 'integer', 'required' => true],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function toJson(TimeRuleDefinition $definition): array
    {
        $rule = $definition->rule;

        return [
            'id' => $definition->id,
            'service_id' => $definition->serviceId,
            'priority' => $definition->priority,
            'active' => $definition->active,
            'weekdays' => $rule->weekdays,
            'from' => LocalTime::fromMinutes($rule->fromMin)->toString(),
            'to' => LocalTime::fromMinutes($rule->toMin)->toString(),
            'valid_from' => $rule->validFrom?->toString(),
            'valid_to' => $rule->validTo?->toString(),
            'percent' => $rule->percent,
        ];
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function fromRequest(\WP_REST_Request $request, int $id): TimeRuleDefinition
    {
        $p = $request->get_params();
        $weekdays = $p['weekdays'] ?? [];
        $serviceId = $p['service_id'] ?? null;

        return new TimeRuleDefinition(
            $id,
            null === $serviceId ? null : self::intValue($serviceId),
            self::intValue($p['priority'] ?? 0),
            (bool) ($p['active'] ?? true),
            new TimeRule(
                $id,
                \is_array($weekdays) ? \array_values(\array_map(self::intValue(...), $weekdays)) : [],
                LocalTime::fromString(self::string($p['from'] ?? null))->minutes,
                LocalTime::fromString(self::string($p['to'] ?? null))->minutes,
                self::date($p['valid_from'] ?? null),
                self::date($p['valid_to'] ?? null),
                self::intValue($p['percent'] ?? null)
            )
        );
    }

    private static function date(mixed $value): ?LocalDate
    {
        return null === $value ? null : LocalDate::fromString(self::string($value));
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
