<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Booking\Application\BookingService;
use Vaqtyar\Modules\Booking\Application\Report;
use Vaqtyar\Modules\Booking\Application\ReportService;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * The dashboard and report figures (docs/api.md):
 *
 *     GET /reports/summary?from=&to=&location=   totals, per status, per day, per service and per staff
 *
 * from and to are local dates, both inclusive, at most 366 days apart. It
 * needs the bookings capability; ReportService checks it again.
 */
final class ReportRoutes
{
    private const DATE = ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$', 'required' => true];

    /**
     * @param \Closure(): ReportService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $this->router->add(
            '/reports/summary',
            'GET',
            fn (\WP_REST_Request $request): array => self::toJson(($this->service)()->summary(
                LocalDate::fromString(self::string($request->get_param('from'))),
                LocalDate::fromString(self::string($request->get_param('to'))),
                self::locationOrNull($request->get_param('location'))
            )),
            static fn (): bool => \current_user_can(Caps::name(BookingService::CAPABILITY)),
            [
                'from' => self::DATE,
                'to' => self::DATE,
                'location' => ['type' => 'integer', 'minimum' => 1],
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function toJson(Report $report): array
    {
        return [
            'from' => $report->from->toString(),
            'to' => $report->to->toString(),
            'totals' => [
                'appointments' => $report->appointments,
                'revenue' => $report->revenue,
                'cancelled' => $report->cancelled,
                'no_show' => $report->noShow,
                'cancel_rate' => $report->cancelRate,
            ],
            'statuses' => (object) $report->statuses,
            'days' => $report->days,
            'services' => $report->services,
            'staff' => $report->staff,
        ];
    }

    private static function locationOrNull(mixed $value): ?int
    {
        return \is_int($value) || \is_numeric($value) ? (int) $value : null;
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : throw new \LogicException('The schema let a wrong type through.');
    }
}
