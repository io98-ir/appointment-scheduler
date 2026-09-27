<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use DateTimeImmutable;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Pagination;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Booking\Application\AppointmentBrowser;
use Vaqtyar\Modules\Booking\Application\AppointmentFilter;
use Vaqtyar\Modules\Booking\Application\AppointmentRow;
use Vaqtyar\Modules\Booking\Application\AppointmentSort;
use Vaqtyar\Modules\Booking\Application\BookingService;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * The admin's reads over appointments (docs/api.md):
 *
 *     GET /appointments        a filtered, sorted page, with X-WP-Total and X-WP-TotalPages
 *     GET /appointments/{id}   one, with its price lines, extras, answers and history
 *     GET /calendar            what takes time in a range, for every or some staff
 *
 * Each needs the bookings capability; AppointmentBrowser checks it again.
 */
final class AppointmentListRoutes
{
    private const ID = ['id' => ['type' => 'integer', 'minimum' => 1, 'required' => true]];

    private const DATE = ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'];

    private const IDS = ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'maxItems' => 100];

    private const AN_ID = ['type' => 'integer', 'minimum' => 1];

    /**
     * @param \Closure(): AppointmentBrowser $browser Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $browser)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(BookingService::CAPABILITY));
        $this->router->add(
            '/appointments',
            'GET',
            fn (\WP_REST_Request $request): \WP_REST_Response => $this->list($request),
            $allowed,
            Pagination::ARGS + [
                'status' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => \array_map(
                            static fn (AppointmentStatus $status): string => $status->value,
                            AppointmentStatus::cases()
                        ),
                    ],
                ],
                'staff' => self::IDS,
                'service' => self::AN_ID,
                'location' => self::AN_ID,
                'customer' => self::AN_ID,
                'from' => self::DATE,
                'to' => self::DATE,
                'search' => ['type' => 'string', 'maxLength' => 100, 'default' => ''],
                'orderby' => ['type' => 'string', 'enum' => ['start', 'created'], 'default' => 'start'],
                'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
            ]
        );
        $this->router->add(
            '/appointments/(?P<id>\d+)',
            'GET',
            fn (\WP_REST_Request $request): array => AppointmentJson::detail(
                ($this->browser)()->appointment(self::id($request))
            ),
            $allowed,
            self::ID
        );
        $this->router->add(
            '/calendar',
            'GET',
            fn (\WP_REST_Request $request): array => \array_map(
                AppointmentJson::row(...),
                ($this->browser)()->calendar(
                    self::time($request->get_param('from')),
                    self::time($request->get_param('to')),
                    self::ids($request->get_param('staff')),
                    self::intOrNull($request->get_param('location'))
                )
            ),
            $allowed,
            [
                'from' => ['type' => 'string', 'format' => 'date-time', 'required' => true],
                'to' => ['type' => 'string', 'format' => 'date-time', 'required' => true],
                'staff' => self::IDS,
                'location' => self::AN_ID,
            ]
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private function list(\WP_REST_Request $request): \WP_REST_Response
    {
        $pagination = Pagination::fromRequest($request);
        $statuses = $request->get_param('status');
        $filter = new AppointmentFilter(
            \is_array($statuses)
                ? \array_values(\array_map(static fn (mixed $s): AppointmentStatus => AppointmentStatus::from(
                    \is_string($s) ? $s : throw new \LogicException('The schema let a wrong type through.')
                ), $statuses))
                : [],
            self::ids($request->get_param('staff')),
            self::intOrNull($request->get_param('service')),
            self::intOrNull($request->get_param('location')),
            self::intOrNull($request->get_param('customer')),
            self::date($request->get_param('from')),
            self::date($request->get_param('to')),
        );
        $search = $request->get_param('search');
        $ascending = 'asc' === $request->get_param('order');
        $sort = 'created' === $request->get_param('orderby')
            ? ($ascending ? AppointmentSort::CreatedAsc : AppointmentSort::CreatedDesc)
            : ($ascending ? AppointmentSort::StartAsc : AppointmentSort::StartDesc);
        $page = ($this->browser)()->list(
            $filter,
            \is_string($search) ? $search : '',
            $sort,
            $pagination->offset(),
            $pagination->perPage
        );

        return $pagination->response(
            \array_map(static fn (AppointmentRow $row): array => AppointmentJson::row($row), $page->items),
            $page->total
        );
    }

    /**
     * An instant with its offset, as the routes return them or as
     * JavaScript's toISOString() writes it (with milliseconds and Z).
     */
    private static function time(mixed $value): int
    {
        $value = \is_string($value) ? $value : '';
        foreach (['!Y-m-d\TH:i:sP', '!Y-m-d\TH:i:s.uP'] as $format) {
            $time = DateTimeImmutable::createFromFormat($format, $value);
            if (false !== $time) {
                return $time->getTimestamp();
            }
        }

        throw new InvalidValue('invalid_time', 'A time is ISO 8601 with its offset, as the routes give it.');
    }

    private static function date(mixed $value): ?LocalDate
    {
        return \is_string($value) ? LocalDate::fromString($value) : null;
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        return \is_array($value) ? \array_values(\array_map(self::int(...), $value)) : [];
    }

    private static function intOrNull(mixed $value): ?int
    {
        return null === $value ? null : self::int($value);
    }

    /**
     * The schema has made it an integer.
     */
    private static function int(mixed $value): int
    {
        return \is_int($value) || \is_numeric($value) ? (int) $value : throw new \LogicException('Not an integer.');
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function id(\WP_REST_Request $request): int
    {
        // From the path only: get_param() prefers the JSON body.
        return self::int($request->get_url_params()['id'] ?? null);
    }
}
