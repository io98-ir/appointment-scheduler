<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Scheduling\Application\ScheduleService;
use Vaqtyar\Modules\Scheduling\Domain\ExceptionKind;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\OwnerType;
use Vaqtyar\Modules\Scheduling\Domain\RuleKind;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleException;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;

/**
 * The admin schedule API (docs/api.md):
 *
 *     GET|PUT /schedules/{owner_type}/{owner_id}   the weekly schedule, saved whole
 *     GET     /schedule-exceptions                 one owner's, between two dates
 *     POST    /schedule-exceptions                 201
 *     PUT     /schedule-exceptions/{id}
 *     DELETE  /schedule-exceptions/{id}            204
 *
 * Times are "HH:MM" wall-clock times of the owner's location, dates
 * "YYYY-MM-DD"; a weekday is 0 (Saturday) to 6 (Friday).
 */
final class ScheduleRoutes
{
    private const OWNER_TYPES = ['staff', 'resource', 'location'];
    private const TIME = ['type' => 'string', 'pattern' => '^\d{2}:\d{2}$'];
    private const DATE = ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$', 'required' => true];

    /** Seven days of a few ranges each, with room to spare. */
    private const MAX_RULES = 100;

    /**
     * @param \Closure(): ScheduleService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(ScheduleService::CAPABILITY));
        $weekly = '/schedules/(?P<owner_type>' . \implode('|', self::OWNER_TYPES) . ')/(?P<owner_id>\d+)';
        $item = '/schedule-exceptions/(?P<id>\d+)';
        $id = ['id' => ['type' => 'integer', 'minimum' => 1, 'required' => true]];
        $owner = [
            'owner_type' => ['type' => 'string', 'enum' => self::OWNER_TYPES, 'required' => true],
            'owner_id' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
        ];
        $exception = $owner + [
            'date' => self::DATE,
            'start' => ['type' => ['string', 'null'], 'pattern' => '^\d{2}:\d{2}$', 'default' => null],
            'end' => ['type' => ['string', 'null'], 'pattern' => '^\d{2}:\d{2}$', 'default' => null],
            'kind' => [
                'type' => 'string',
                'enum' => \array_map(static fn (ExceptionKind $k): string => $k->value, ExceptionKind::cases()),
                'required' => true,
            ],
            'note' => ['type' => 'string', 'maxLength' => 1000, 'default' => ''],
        ];

        $this->router->add(
            $weekly,
            'GET',
            fn (\WP_REST_Request $request): array => self::weeklyJson(
                ($this->service)()->weekly(self::owner($request->get_url_params()))
            ),
            $allowed,
            $owner
        );
        $this->router->add(
            $weekly,
            'PUT',
            function (\WP_REST_Request $request): array {
                $owner = self::owner($request->get_url_params());
                $rules = \array_map(
                    static fn (array $rule): ScheduleRule => new ScheduleRule(
                        null,
                        $owner,
                        self::int($rule['weekday'] ?? null),
                        LocalTime::fromString(self::string($rule['start'] ?? null)),
                        LocalTime::fromString(self::string($rule['end'] ?? null)),
                        RuleKind::from(self::string($rule['kind'] ?? 'work'))
                    ),
                    self::objects($request->get_param('rules'))
                );

                return self::weeklyJson(($this->service)()->replaceWeekly($owner, $rules));
            },
            $allowed,
            $owner + [
                'rules' => [
                    'type' => 'array',
                    'required' => true,
                    'maxItems' => self::MAX_RULES,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'weekday' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 6, 'required' => true],
                            'start' => self::TIME + ['required' => true],
                            'end' => self::TIME + ['required' => true],
                            'kind' => ['type' => 'string', 'enum' => ['work', 'break'], 'default' => 'work'],
                        ],
                    ],
                ],
            ]
        );
        $this->router->add(
            '/schedule-exceptions',
            'GET',
            fn (\WP_REST_Request $request): array => \array_map(
                self::exceptionJson(...),
                ($this->service)()->exceptions(
                    self::owner($request->get_params()),
                    LocalDate::fromString(self::string($request->get_param('from'))),
                    LocalDate::fromString(self::string($request->get_param('to')))
                )
            ),
            $allowed,
            $owner + ['from' => self::DATE, 'to' => self::DATE]
        );
        $this->router->add(
            '/schedule-exceptions',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => new \WP_REST_Response(
                self::exceptionJson(($this->service)()->saveException(self::exception($request, null))),
                201
            ),
            $allowed,
            $exception
        );
        $this->router->add(
            $item,
            'PUT',
            fn (\WP_REST_Request $request): array => self::exceptionJson(
                ($this->service)()->saveException(self::exception($request, self::id($request)))
            ),
            $allowed,
            $id + $exception
        );
        $this->router->add(
            $item,
            'DELETE',
            function (\WP_REST_Request $request): \WP_REST_Response {
                ($this->service)()->deleteException(self::id($request));

                return new \WP_REST_Response(null, 204);
            },
            $allowed,
            $id
        );
    }

    /**
     * @param list<ScheduleRule> $rules
     * @return array{rules: list<array{weekday: int, start: string, end: string, kind: string}>}
     */
    private static function weeklyJson(array $rules): array
    {
        return ['rules' => \array_map(
            static fn (ScheduleRule $rule): array => [
                'weekday' => $rule->weekday,
                'start' => $rule->start->toString(),
                'end' => $rule->end->toString(),
                'kind' => $rule->kind->value,
            ],
            $rules
        )];
    }

    /**
     * @return array<string, mixed>
     */
    private static function exceptionJson(ScheduleException $exception): array
    {
        return [
            'id' => $exception->id,
            'owner_type' => $exception->owner->type->value,
            'owner_id' => $exception->owner->id,
            'date' => $exception->date->toString(),
            'start' => $exception->start?->toString(),
            'end' => $exception->end?->toString(),
            'kind' => $exception->kind->value,
            'note' => $exception->note,
        ];
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function exception(\WP_REST_Request $request, ?int $id): ScheduleException
    {
        $start = $request->get_param('start');
        $end = $request->get_param('end');

        return new ScheduleException(
            $id,
            self::owner($request->get_params()),
            LocalDate::fromString(self::string($request->get_param('date'))),
            null === $start ? null : LocalTime::fromString(self::string($start)),
            null === $end ? null : LocalTime::fromString(self::string($end)),
            ExceptionKind::from(self::string($request->get_param('kind'))),
            \trim(self::string($request->get_param('note')))
        );
    }

    /**
     * @param array<mixed> $params
     */
    private static function owner(array $params): Owner
    {
        return new Owner(
            OwnerType::from(self::string($params['owner_type'] ?? null)),
            self::int($params['owner_id'] ?? null)
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function id(\WP_REST_Request $request): int
    {
        // From the path only, as CrudRoutes does: the body may carry an "id".
        return self::int($request->get_url_params()['id'] ?? null);
    }

    /**
     * @return list<array<mixed>>
     */
    private static function objects(mixed $value): array
    {
        return \is_array($value)
            ? \array_values(\array_map(
                static fn (mixed $item): array => \is_array($item) ? $item : throw self::wrongType(),
                $value
            ))
            : throw self::wrongType();
    }

    /**
     * The schema has checked the type; path parameters arrive as strings.
     */
    private static function int(mixed $value): int
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
