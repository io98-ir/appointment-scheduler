<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use DateTimeImmutable;
use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Booking\Application\AppointmentChange;
use Vaqtyar\Modules\Booking\Application\CustomerPanel;
use Vaqtyar\Modules\Booking\Application\PanelAppointment;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * The customer's own appointments (docs/api.md). Public routes: the customer
 * is the phone session in the X-Phone-Session header (from POST /otp/verify),
 * and the REST nonce shows the request came from the site.
 *
 *     GET  /my/appointments                    the customer's newest, with what the policy says
 *     POST /my/appointments/{id}/cancel        cancel; `reason` optional
 *     POST /my/appointments/{id}/reschedule    move to `start`
 */
final class PanelRoutes
{
    public const SESSION_HEADER = 'X-Phone-Session';

    private const LIMIT = 60;

    private const ID = ['id' => ['type' => 'integer', 'minimum' => 1, 'required' => true]];

    /**
     * @param \Closure(): CustomerPanel $panel Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $panel)
    {
    }

    public function register(): void
    {
        $limit = new RateLimit(self::LIMIT, 60);
        $this->router->add(
            '/my/appointments',
            'GET',
            fn (\WP_REST_Request $request): array => \array_map(
                self::item(...),
                ($this->panel)()->appointments(self::session($request))
            ),
            Router::hasRestNonce(...),
            [],
            $limit
        );
        $this->router->add(
            '/my/appointments/(?P<id>\d+)/cancel',
            'POST',
            fn (\WP_REST_Request $request): array => self::change(($this->panel)()->cancel(
                self::session($request),
                self::id($request),
                self::reason($request->get_param('reason'))
            )),
            Router::hasRestNonce(...),
            self::ID + ['reason' => ['type' => 'string', 'maxLength' => 500, 'default' => '']],
            $limit
        );
        $this->router->add(
            '/my/appointments/(?P<id>\d+)/reschedule',
            'POST',
            fn (\WP_REST_Request $request): array => self::change(($this->panel)()->reschedule(
                self::session($request),
                self::id($request),
                self::start($request->get_param('start'))
            )),
            Router::hasRestNonce(...),
            self::ID + ['start' => ['type' => 'string', 'format' => 'date-time', 'required' => true]],
            $limit
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(PanelAppointment $item): array
    {
        return AppointmentJson::row($item->row) + [
            'cancel' => $item->cancel?->toArray(),
            'reschedule' => $item->reschedule?->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function change(AppointmentChange $change): array
    {
        return AppointmentJson::of($change->id, $change->appointment) + ['decision' => $change->decision->toArray()];
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function session(\WP_REST_Request $request): ?string
    {
        $token = $request->get_header(self::SESSION_HEADER);

        return \is_string($token) && '' !== $token ? $token : null;
    }

    /**
     * From the path only: the body may carry an "id".
     *
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function id(\WP_REST_Request $request): int
    {
        $id = $request->get_url_params()['id'] ?? null;

        return \is_numeric($id) ? (int) $id : throw new \LogicException('The route pattern makes the id numeric.');
    }

    private static function reason(mixed $value): ?string
    {
        $reason = \is_string($value) ? \trim(\sanitize_textarea_field($value)) : '';

        return '' === $reason ? null : $reason;
    }

    /**
     * An offered start: ISO 8601 with its offset, as GET /availability gives it.
     */
    private static function start(mixed $value): int
    {
        $start = \is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value) : false;
        if (false === $start) {
            throw new InvalidValue('invalid_start', 'The start is a time with its offset, as availability gives it.');
        }

        return $start->getTimestamp();
    }
}
