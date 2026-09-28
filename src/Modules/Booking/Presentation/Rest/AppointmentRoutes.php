<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use DateTimeImmutable;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Booking\Application\Actor;
use Vaqtyar\Modules\Booking\Application\AppointmentChange;
use Vaqtyar\Modules\Booking\Application\AppointmentService;
use Vaqtyar\Modules\Booking\Application\BookingService;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * Staff change an appointment (docs/api.md): cancel and reschedule, as
 * the service's policy allows unless overridden; no-show, complete,
 * approve and the internal note. The
 * customer's own routes come with the customer panel (T4.4).
 */
final class AppointmentRoutes
{
    private const ID = '/appointments/(?P<id>\d+)';

    private const REASON = ['type' => 'string', 'maxLength' => 1000];

    private const OVERRIDE = ['type' => 'boolean', 'default' => false];

    /**
     * @param \Closure(): AppointmentService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(BookingService::CAPABILITY));
        $this->router->add(
            self::ID . '/cancel',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => $this->cancel($request),
            $allowed,
            ['reason' => self::REASON, 'override' => self::OVERRIDE]
        );
        $this->router->add(
            self::ID . '/reschedule',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => $this->reschedule($request),
            $allowed,
            [
                'start' => ['type' => 'string', 'format' => 'date-time', 'required' => true],
                'staff' => ['type' => 'integer', 'minimum' => 1],
                'reason' => self::REASON,
                'override' => self::OVERRIDE,
            ]
        );
        $this->router->add(
            self::ID . '/no-show',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => $this->noShow($request),
            $allowed
        );
        $this->router->add(
            self::ID . '/complete',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => $this->complete($request),
            $allowed
        );
        $this->router->add(
            self::ID . '/approve',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => $this->approve($request),
            $allowed
        );
        $this->router->add(
            self::ID . '/note',
            'PUT',
            fn (\WP_REST_Request $request): \WP_REST_Response => $this->note($request),
            $allowed,
            ['note' => ['type' => 'string', 'maxLength' => 5000, 'required' => true]]
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = self::int($request->get_param('id'));

        return new \WP_REST_Response(
            AppointmentJson::of($id, ($this->service)()->complete($id, \get_current_user_id()))
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private function approve(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = self::int($request->get_param('id'));

        return new \WP_REST_Response(
            AppointmentJson::of($id, ($this->service)()->approve($id, \get_current_user_id()))
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private function note(\WP_REST_Request $request): \WP_REST_Response
    {
        $note = $request->get_param('note');
        ($this->service)()->saveNote(
            self::int($request->get_param('id')),
            \get_current_user_id(),
            \is_string($note) ? \sanitize_textarea_field($note) : ''
        );

        return new \WP_REST_Response(null, 204);
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private function cancel(\WP_REST_Request $request): \WP_REST_Response
    {
        return self::changed(($this->service)()->cancel(
            self::int($request->get_param('id')),
            Actor::user(\get_current_user_id()),
            self::reason($request),
            true === $request->get_param('override')
        ));
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private function reschedule(\WP_REST_Request $request): \WP_REST_Response
    {
        $start = $request->get_param('start');
        $time = \is_string($start) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $start) : false;
        if (false === $time) {
            throw new InvalidValue('invalid_start', 'The start is a time with its offset, as availability gives it.');
        }
        $staff = $request->get_param('staff');

        return self::changed(($this->service)()->reschedule(
            self::int($request->get_param('id')),
            Actor::user(\get_current_user_id()),
            $time->getTimestamp(),
            null === $staff ? null : self::int($staff),
            self::reason($request),
            true === $request->get_param('override')
        ));
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private function noShow(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = self::int($request->get_param('id'));

        return new \WP_REST_Response(
            AppointmentJson::of($id, ($this->service)()->markNoShow($id, \get_current_user_id()))
        );
    }

    private static function changed(AppointmentChange $change): \WP_REST_Response
    {
        return new \WP_REST_Response(
            AppointmentJson::of($change->id, $change->appointment) + [
                'decision' => $change->decision->toArray(),
                'overridden' => $change->overridden,
            ]
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function reason(\WP_REST_Request $request): ?string
    {
        $reason = $request->get_param('reason');
        $reason = \is_string($reason) ? \trim(\sanitize_textarea_field($reason)) : '';

        return '' === $reason ? null : $reason;
    }

    /**
     * The route's pattern or schema has made it an integer.
     */
    private static function int(mixed $value): int
    {
        return \is_int($value) || \is_numeric($value) ? (int) $value : throw new \LogicException('Not an integer.');
    }
}
