<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Scheduling\Application\HolidayService;
use Vaqtyar\Modules\Scheduling\Domain\Holiday;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\Slug;

/**
 * The admin holiday-calendar API:
 *
 *     GET    /holidays?calendar=&from=&to=   a calendar's days off, by date
 *     POST   /holidays                       add, or replace the day's title: 201
 *     DELETE /holidays/{calendar}/{date}     204
 *
 * A location names its calendar (T1.3); this screen edits any calendar by
 * its key, since a location's own screen only stores the key it uses.
 */
final class HolidayRoutes
{
    private const DATE = ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$', 'required' => true];
    private const CALENDAR = ['type' => 'string', 'maxLength' => 64, 'required' => true];
    private const ITEM = '/holidays/(?P<calendar>[a-z0-9_-]+)/(?P<date>\d{4}-\d{2}-\d{2})';

    /**
     * @param \Closure(): HolidayService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(HolidayService::CAPABILITY));

        $this->router->add(
            '/holidays',
            'GET',
            fn (\WP_REST_Request $request): array => \array_map(
                self::toJson(...),
                ($this->service)()->holidays(
                    Slug::fromInput(self::string($request->get_param('calendar'))),
                    LocalDate::fromString(self::string($request->get_param('from'))),
                    LocalDate::fromString(self::string($request->get_param('to')))
                )
            ),
            $allowed,
            ['calendar' => self::CALENDAR, 'from' => self::DATE, 'to' => self::DATE]
        );
        $this->router->add(
            '/holidays',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => new \WP_REST_Response(
                self::toJson(($this->service)()->save(
                    Slug::fromInput(self::string($request->get_param('calendar'))),
                    LocalDate::fromString(self::string($request->get_param('date'))),
                    Name::fromInput(self::string($request->get_param('title')))
                )),
                201
            ),
            $allowed,
            ['calendar' => self::CALENDAR, 'date' => self::DATE, 'title' => ['type' => 'string', 'required' => true]]
        );
        $this->router->add(
            self::ITEM,
            'DELETE',
            function (\WP_REST_Request $request): \WP_REST_Response {
                $params = $request->get_url_params();
                ($this->service)()->delete(
                    Slug::fromInput(self::string($params['calendar'] ?? null)),
                    LocalDate::fromString(self::string($params['date'] ?? null))
                );

                return new \WP_REST_Response(null, 204);
            },
            $allowed,
            [
                'calendar' => ['type' => 'string', 'required' => true],
                'date' => ['type' => 'string', 'required' => true],
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function toJson(Holiday $holiday): array
    {
        return [
            'calendar' => $holiday->calendar->value,
            'date' => $holiday->date->toString(),
            'title' => $holiday->title->value,
            'source' => $holiday->source->value,
        ];
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : throw new \LogicException('The schema let a wrong type through.');
    }
}
