<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Presentation\Rest;

use DateTimeImmutable;
use DateTimeZone;
use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Scheduling\Application\Availability;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Modules\Scheduling\Application\AvailabilityService;
use Vaqtyar\Modules\Scheduling\Application\DayAvailability;
use Vaqtyar\Modules\Scheduling\Domain\Availability\Slot;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * The public GET /availability (docs/api.md): anyone may ask, within a rate
 * limit per client, like the booking widget does before any login.
 */
final class AvailabilityRoutes
{
    /** Per client and minute: a month view and a few days a second, with room for retries. */
    private const LIMIT = 120;

    private const ARGS = [
        'variant' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
        'location' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
        'view' => ['type' => 'string', 'enum' => ['day', 'month', 'first'], 'default' => 'day'],
        'date' => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
        'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => AvailabilityService::MAX_DAYS, 'default' => 31],
        'staff' => ['type' => 'integer', 'minimum' => 1],
        'extras' => [
            'type' => 'array',
            'items' => ['type' => 'integer', 'minimum' => 1],
            'maxItems' => 100,
            'default' => [],
        ],
        'party_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 1],
    ];

    /**
     * @param \Closure(): AvailabilityService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $this->router->add(
            '/availability',
            'GET',
            fn (\WP_REST_Request $request): array => $this->respond($request),
            Router::ANYONE,
            self::ARGS,
            new RateLimit(self::LIMIT, 60)
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     * @return array<string, mixed>
     */
    private function respond(\WP_REST_Request $request): array
    {
        $staff = $request->get_param('staff');
        $query = new AvailabilityQuery(
            self::int($request->get_param('variant')),
            self::int($request->get_param('location')),
            null === $staff ? null : self::int($staff),
            \array_map(self::int(...), \array_values((array) $request->get_param('extras'))),
            self::int($request->get_param('party_size'))
        );
        $date = $request->get_param('date');
        $date = \is_string($date) ? LocalDate::fromString($date) : null;
        $days = self::int($request->get_param('days'));
        $service = ($this->service)();

        return match ($request->get_param('view')) {
            'month' => self::month($service->month($query, $date, $days)),
            'first' => self::first($service->first($query, $date, $days)),
            default => self::day($service->day($query, $date)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function day(Availability $availability): array
    {
        $day = $availability->days[0];

        return [
            'timezone' => $availability->timezone->getName(),
            'date' => $day->date->toString(),
            'status' => $day->status->value,
            'slots' => self::slots($day, $availability->timezone),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function month(Availability $availability): array
    {
        return [
            'timezone' => $availability->timezone->getName(),
            'days' => \array_map(
                static fn (DayAvailability $day): array => [
                    'date' => $day->date->toString(),
                    'status' => $day->status->value,
                ],
                $availability->days
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function first(Availability $availability): array
    {
        $day = $availability->days[0] ?? null;

        return [
            'timezone' => $availability->timezone->getName(),
            'date' => $day?->date->toString(),
            'slots' => null === $day ? [] : self::slots($day, $availability->timezone),
        ];
    }

    /**
     * Each start with the staff who can take it, the first one assigned,
     * and that one's end and free seats.
     *
     * @return list<array{start: string, end: string, staff_ids: list<int>, seats_left: int}>
     */
    private static function slots(DayAvailability $day, DateTimeZone $zone): array
    {
        return \array_map(
            static fn (Slot $slot): array => [
                'start' => self::time($slot->start, $zone),
                'end' => self::time($slot->staff[0]->end, $zone),
                'staff_ids' => $slot->staffIds(),
                'seats_left' => $slot->staff[0]->seatsLeft,
            ],
            $day->slots
        );
    }

    private static function time(int $timestamp, DateTimeZone $zone): string
    {
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone($zone)->format(\DATE_ATOM);
    }

    /**
     * The route's schema has made it an integer.
     */
    private static function int(mixed $value): int
    {
        return \is_int($value) || \is_numeric($value) ? (int) $value : throw new \LogicException('Not an integer.');
    }
}
